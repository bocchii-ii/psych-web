<?php

namespace App\Services;

use App\Events\GameEnded;
use App\Events\GameStarted;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\PlayerSubmitted;
use App\Events\PlayerVoted;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Events\VotingStarted;
use App\Jobs\ExpireAnswerPhase;
use App\Jobs\ExpireVotingPhase;
use App\Models\Question;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\RoundSubmission;
use App\Models\RoundVote;
use App\Models\User;
use Illuminate\Support\Str;

class RoomService
{
    public const ANSWER_SECONDS = 60;
    public const VOTING_SECONDS = 30;

    public function __construct(
        private AnswerSanitizer $sanitizer,
        private ScoreCalculator $scorer,
    ) {}

    public function createRoom(User $host, int $totalRounds = 5): Room
    {
        $room = Room::create([
            'code' => $this->generateCode(),
            'host_id' => $host->id,
            'status' => 'waiting',
            'total_rounds' => $totalRounds,
        ]);

        RoomPlayer::create([
            'room_id' => $room->id,
            'user_id' => $host->id,
        ]);

        return $room;
    }

    public function joinRoom(string $code, User $user): Room
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        abort_if($room->status !== 'waiting', 422, 'Game already in progress.');

        RoomPlayer::firstOrCreate([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $playerCount = $room->players()->count();

        broadcast(new PlayerJoined($room->code, $user->id, $user->name, $playerCount))->toOthers();

        return $room;
    }

    public function leaveRoom(Room $room, User $user): void
    {
        $room->players()->where('user_id', $user->id)->delete();

        $newHostId = null;
        if ($room->host_id === $user->id) {
            $next = $room->players()->orderBy('joined_at')->first();
            if ($next) {
                $room->update(['host_id' => $next->user_id]);
                $newHostId = $next->user_id;
            } else {
                $room->delete();
                return;
            }
        }

        broadcast(new PlayerLeft($room->code, $user->id, $user->name, $newHostId));
    }

    public function startGame(Room $room): void
    {
        $room->update(['status' => 'question', 'current_round' => 0]);

        broadcast(new GameStarted($room->code, $room->total_rounds));

        $this->startNextRound($room);
    }

    public function startNextRound(Room $room): void
    {
        $nextRound = $room->current_round + 1;
        $room->update(['current_round' => $nextRound, 'status' => 'question']);

        $question = Question::inRandomOrder()->first();

        // Store question on room for reference during reveal
        $room->update(['status' => 'question']);
        cache()->put("room:{$room->id}:round:{$nextRound}:question", $question->id, 3600);
        cache()->put("room:{$room->id}:round:{$nextRound}:correct", $question->correct_answer, 3600);

        broadcast(new RoundStarted(
            $room->code,
            $nextRound,
            $room->total_rounds,
            $question->body,
            self::ANSWER_SECONDS,
        ));

        ExpireAnswerPhase::dispatch($room->id, $nextRound)
            ->delay(now()->addSeconds(self::ANSWER_SECONDS));
    }

    public function submitAnswer(Room $room, User $user, string $rawAnswer): RoundSubmission
    {
        abort_if($room->status !== 'question', 422, 'Answer phase is not active.');

        $sanitized = $this->sanitizer->sanitize($rawAnswer);
        abort_if($sanitized === '', 422, 'Answer cannot be empty after sanitization.');

        $correctAnswer = cache()->get("room:{$room->id}:round:{$room->current_round}:correct");
        abort_if($sanitized === $correctAnswer, 422, 'Too close to the real answer! Try something else.');

        $duplicate = RoundSubmission::where('room_id', $room->id)
            ->where('round_number', $room->current_round)
            ->where('sanitized_answer', $sanitized)
            ->where('user_id', '!=', $user->id)
            ->exists();

        abort_if($duplicate, 422, 'Someone already submitted that answer. Try rephrasing.');

        $submission = RoundSubmission::updateOrCreate(
            [
                'room_id' => $room->id,
                'round_number' => $room->current_round,
                'user_id' => $user->id,
            ],
            [
                'raw_answer' => $rawAnswer,
                'sanitized_answer' => $sanitized,
            ]
        );

        $submittedCount = $room->currentRoundSubmissions()->count();
        $totalCount = $room->players()->count();

        broadcast(new PlayerSubmitted($room->code, $user->name, $submittedCount, $totalCount));

        if ($submittedCount >= $totalCount) {
            $this->startVoting($room);
        }

        return $submission;
    }

    public function startVoting(Room $room): void
    {
        if ($room->status !== 'question') {
            return;
        }

        $room->update(['status' => 'voting']);

        $correctAnswer = cache()->get("room:{$room->id}:round:{$room->current_round}:correct");

        $submissions = $room->currentRoundSubmissions()->get();

        $answers = $submissions->map(fn ($s) => [
            'id' => $s->id,
            'text' => $s->sanitized_answer,
            'is_correct' => false,
        ])->push([
            'id' => null,
            'text' => $correctAnswer,
            'is_correct' => true,
        ])->shuffle()->values()->all();

        broadcast(new VotingStarted($room->code, $answers, self::VOTING_SECONDS));

        ExpireVotingPhase::dispatch($room->id, $room->current_round)
            ->delay(now()->addSeconds(self::VOTING_SECONDS));
    }

    public function submitVote(Room $room, User $voter, ?int $submissionId): RoundVote
    {
        abort_if($room->status !== 'voting', 422, 'Voting phase is not active.');

        if ($submissionId !== null) {
            $ownSubmission = RoundSubmission::where('id', $submissionId)
                ->where('user_id', $voter->id)
                ->exists();
            abort_if($ownSubmission, 422, 'You cannot vote for your own answer.');
        }

        $vote = RoundVote::updateOrCreate(
            [
                'room_id' => $room->id,
                'round_number' => $room->current_round,
                'voter_id' => $voter->id,
            ],
            ['submission_id' => $submissionId]
        );

        $votedCount = $room->currentRoundVotes()->count();
        $totalCount = $room->players()->count();

        broadcast(new PlayerVoted($room->code, $voter->name, $votedCount, $totalCount));

        if ($votedCount >= $totalCount) {
            $this->revealRound($room);
        }

        return $vote;
    }

    public function revealRound(Room $room): void
    {
        if ($room->status !== 'voting') {
            return;
        }

        $room->update(['status' => 'reveal']);

        $correctAnswer = cache()->get("room:{$room->id}:round:{$room->current_round}:correct");
        $submissions = $room->currentRoundSubmissions()->with('user', 'votes.voter')->get();

        $points = $this->scorer->calculate($room, $submissions, $correctAnswer);

        // Persist deltas to room_players
        foreach ($points as $userId => $delta) {
            $room->players()->where('user_id', $userId)->increment('score', $delta);
        }

        $answers = $submissions->map(function ($s) use ($points) {
            return [
                'id' => $s->id,
                'text' => $s->sanitized_answer,
                'author' => $s->user->name,
                'voters' => $s->votes->map(fn ($v) => $v->voter->name)->all(),
                'points_earned' => $points[$s->user_id] ?? 0,
                'is_correct' => false,
            ];
        })->push([
            'id' => null,
            'text' => $correctAnswer,
            'author' => null,
            'voters' => $room->currentRoundVotes()->whereNull('submission_id')->with('voter')->get()
                ->map(fn ($v) => $v->voter->name)->all(),
            'points_earned' => 0,
            'is_correct' => true,
        ])->all();

        $leaderboard = $room->players()->with('user')->orderByDesc('score')->get()
            ->map(fn ($rp) => [
                'user_id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
                'delta' => $points[$rp->user_id] ?? 0,
            ])->all();

        broadcast(new RoundRevealed($room->code, $correctAnswer, $answers, $leaderboard));
    }

    public function advanceAfterReveal(Room $room): void
    {
        if ($room->current_round >= $room->total_rounds) {
            $this->endGame($room);
        } else {
            $this->startNextRound($room);
        }
    }

    public function endGame(Room $room): void
    {
        $room->update(['status' => 'finished']);

        $leaderboard = $room->players()->with('user')->orderByDesc('score')->get()
            ->map(fn ($rp) => [
                'user_id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
            ])->all();

        broadcast(new GameEnded($room->code, $leaderboard));
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Room::where('code', $code)->exists());

        return $code;
    }
}
