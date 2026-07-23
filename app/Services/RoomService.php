<?php

namespace App\Services;

use App\Events\GameEnded;
use App\Events\GameStarted;
use App\Events\HostSpectatorModeChanged;
use App\Events\PlayAgainStarted;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\PlayerSubmitted;
use App\Events\PlayerVoted;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Events\VotingStarted;
use App\Jobs\ExpireAnswerPhase;
use App\Jobs\ExpireRevealPhase;
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
    public const VOTING_SECONDS_PER_CHOICE = 10;
    public const REVEAL_SECONDS = 60;

    public function __construct(
        private AnswerSanitizer $sanitizer,
        private ScoreCalculator $scorer,
    ) {}

    public function createRoom(User $host, int $totalRounds = 5, int $maxPlayers = 8, array $excludedCategories = []): Room
    {
        $room = Room::create([
            'code' => $this->generateCode(),
            'host_id' => $host->id,
            'status' => 'waiting',
            'total_rounds' => $totalRounds,
            'max_players' => $maxPlayers,
            'excluded_categories' => $excludedCategories,
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

        abort_if($room->status === 'finished', 422, 'Game has ended.');

        $alreadyJoined = $room->players()->where('user_id', $user->id)->exists();

        if (!$alreadyJoined) {
            // Joining while a round is already underway can only be done as a
            // spectator — there's no way to fold a new active player into
            // gameplay mid-round. The max-player cap only limits active slots,
            // so it doesn't apply to spectators joining an ongoing game.
            $isMidGame = $room->status !== 'waiting';

            abort_if(!$isMidGame && $room->isFull(), 422, 'Room is full.');

            RoomPlayer::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'is_spectator' => $isMidGame,
            ]);
        }

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

    public function setSpectatorMode(Room $room, User $host, bool $isSpectator): void
    {
        abort_if($room->host_id !== $host->id, 403);
        abort_if($room->status !== 'waiting', 422, 'Cannot change spectator mode after the game has started.');

        $room->players()->where('user_id', $host->id)->update(['is_spectator' => $isSpectator]);

        broadcast(new HostSpectatorModeChanged($room->code, $isSpectator));
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

        $excludedCategories = $room->excluded_categories ?? [];

        $question = Question::where(function ($query) {
            $query->whereNull('asked_at')
                ->orWhere('asked_at', '<', now()->startOfWeek());
        })
            ->when($excludedCategories, fn ($query) => $query->whereNotIn('category', $excludedCategories))
            ->inRandomOrder()->first();

        // Fall back to reusing a question if the whole bank (within the room's
        // allowed categories) was already asked this week
        $question ??= Question::when($excludedCategories, fn ($query) => $query->whereNotIn('category', $excludedCategories))
            ->inRandomOrder()->first();

        // Last resort: ignore the exclusions entirely rather than crash, in case
        // a room excludes every category that has an available question.
        $question ??= Question::inRandomOrder()->first();

        $question->update(['asked_at' => now()]);

        $deadline = now()->addSeconds(self::ANSWER_SECONDS);

        // Store question on room for reference during reveal, and for players
        // who load the Game page after (or without) receiving the RoundStarted
        // broadcast — see RoomController::gameProps().
        $room->update(['status' => 'question']);
        cache()->put("room:{$room->id}:round:{$nextRound}:question", $question->id, 3600);
        cache()->put("room:{$room->id}:round:{$nextRound}:correct", $question->correct_answer, 3600);
        cache()->put("room:{$room->id}:round:{$nextRound}:question_body", $question->body, 3600);
        cache()->put("room:{$room->id}:round:{$nextRound}:deadline", $deadline->toIso8601String(), 3600);

        broadcast(new RoundStarted(
            $room->code,
            $nextRound,
            $room->total_rounds,
            $question->body,
            self::ANSWER_SECONDS,
        ));

        ExpireAnswerPhase::dispatch($room->id, $nextRound)->delay($deadline);
    }

    public function submitAnswer(Room $room, User $user, string $rawAnswer): RoundSubmission
    {
        abort_if($room->status !== 'question', 422, 'Answer phase is not active.');

        $isSpectator = $room->players()->where('user_id', $user->id)->value('is_spectator');
        abort_if($isSpectator, 422, 'Spectators cannot submit answers.');

        $sanitized = $this->sanitizer->sanitize($rawAnswer);
        abort_if($sanitized === '', 422, 'Answer cannot be empty after sanitization.');

        $correctAnswer = cache()->get("room:{$room->id}:round:{$room->current_round}:correct");
        abort_if($sanitized === $correctAnswer, 422, 'Someone already submitted that answer. Try rephrasing.');

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
                'raw_answer' => trim($rawAnswer),
                'sanitized_answer' => $sanitized,
            ]
        );

        $submittedCount = $room->currentRoundSubmissions()->count();
        $totalCount = $room->activePlayers()->count();

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
            'text' => $s->raw_answer,
            'is_correct' => false,
        ])->push([
            'id' => null,
            'text' => $correctAnswer,
            'is_correct' => true,
        ])->shuffle()->values()->all();

        // Scale voting time with the number of choices so larger rooms (more
        // fake answers to read) still have enough time to decide.
        $votingSeconds = count($answers) * self::VOTING_SECONDS_PER_CHOICE;
        $deadline = now()->addSeconds($votingSeconds);

        // Cache the shuffled answers and deadline (not just broadcast them) so
        // a player who reloads mid-voting can still be shown the choices —
        // see RoomController::gameProps().
        cache()->put("room:{$room->id}:round:{$room->current_round}:answers", $answers, 3600);
        cache()->put("room:{$room->id}:round:{$room->current_round}:voting_deadline", $deadline->toIso8601String(), 3600);

        broadcast(new VotingStarted($room->code, $answers, $votingSeconds));

        ExpireVotingPhase::dispatch($room->id, $room->current_round)->delay($deadline);
    }

    public function submitVote(Room $room, User $voter, ?int $submissionId): RoundVote
    {
        abort_if($room->status !== 'voting', 422, 'Voting phase is not active.');

        $isSpectator = $room->players()->where('user_id', $voter->id)->value('is_spectator');
        abort_if($isSpectator, 422, 'Spectators cannot vote.');

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
        $totalCount = $room->activePlayers()->count();

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

        // Track how many players each submission fooled (for "Psyched Most
        // Players") and how many times each voter fell for a fake answer
        // (for "Most Gullible Player"), for the end-game superlatives.
        foreach ($submissions as $s) {
            if ($s->votes->isNotEmpty()) {
                $room->players()->where('user_id', $s->user_id)->increment('times_fooled', $s->votes->count());

                foreach ($s->votes as $vote) {
                    $room->players()->where('user_id', $vote->voter_id)->increment('times_gullible');
                }
            }
        }

        $answers = $submissions->map(function ($s) use ($points) {
            return [
                'id' => $s->id,
                'text' => $s->raw_answer,
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
        ])->shuffle()->values()->all();

        $leaderboard = $room->activePlayers()->with('user')->orderByDesc('score')->get()
            ->map(fn ($rp) => [
                'user_id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
                'delta' => $points[$rp->user_id] ?? 0,
            ])->all();

        $deadline = now()->addSeconds(self::REVEAL_SECONDS);
        cache()->put("room:{$room->id}:round:{$room->current_round}:reveal_deadline", $deadline->toIso8601String(), 3600);

        broadcast(new RoundRevealed($room->code, $correctAnswer, $answers, $leaderboard, self::REVEAL_SECONDS));

        ExpireRevealPhase::dispatch($room->id, $room->current_round)->delay($deadline);
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

        $leaderboard = $room->activePlayers()->with('user')->orderByDesc('score')->get()
            ->map(fn ($rp) => [
                'user_id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
            ])->all();

        broadcast(new GameEnded($room->code, $leaderboard));
    }

    public function playAgain(Room $oldRoom, User $host): Room
    {
        abort_if($oldRoom->host_id !== $host->id, 403);
        abort_if($oldRoom->status !== 'finished', 422, 'Game is not finished yet.');

        $newRoom = $this->createRoom($host, $oldRoom->total_rounds, $oldRoom->max_players, $oldRoom->excluded_categories ?? []);

        $otherPlayers = $oldRoom->players()->where('user_id', '!=', $host->id)->with('user')->get();
        foreach ($otherPlayers as $player) {
            $this->joinRoom($newRoom->code, $player->user);
        }

        broadcast(new PlayAgainStarted($oldRoom->code, $newRoom->code))->toOthers();

        return $newRoom;
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Room::where('code', $code)->exists());

        return $code;
    }
}
