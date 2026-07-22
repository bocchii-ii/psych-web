<?php

namespace Tests\Feature\Game;

use App\Events\GameEnded;
use App\Events\GameStarted;
use App\Events\PlayAgainStarted;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\PlayerSubmitted;
use App\Events\PlayerVoted;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Events\VotingStarted;
use App\Jobs\ExpireRevealPhase;
use App\Models\Question;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RoomServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $service;

    private User $host;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        $this->service = app(RoomService::class);
        $this->host = User::factory()->create();
        $this->guest = User::factory()->create();

        Question::create([
            'body' => 'What is the capital of France?',
            'correct_answer' => 'paris',
            'category' => 'Geography',
        ]);
    }

    // ── createRoom ────────────────────────────────────────────────────────────

    public function test_create_room_creates_room_and_adds_host(): void
    {
        $room = $this->service->createRoom($this->host, 5);

        $this->assertSame($this->host->id, $room->host_id);
        $this->assertSame(5, $room->total_rounds);
        $this->assertSame('waiting', $room->status);
        $this->assertSame(1, $room->players()->count());
    }

    public function test_create_room_generates_unique_uppercase_code(): void
    {
        $room = $this->service->createRoom($this->host);

        $this->assertSame(6, strlen($room->code));
        $this->assertSame(strtoupper($room->code), $room->code);
    }

    // ── joinRoom ──────────────────────────────────────────────────────────────

    public function test_join_room_adds_guest_and_broadcasts_player_joined(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->assertSame(2, $room->players()->count());
        Event::assertDispatched(PlayerJoined::class);
    }

    public function test_join_room_is_idempotent(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->joinRoom($room->code, $this->guest);

        $this->assertSame(2, $room->players()->count());
    }

    public function test_join_room_rejects_when_game_in_progress(): void
    {
        $room = $this->service->createRoom($this->host);
        $room->update(['status' => 'question']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->joinRoom($room->code, $this->guest);
    }

    // ── leaveRoom ─────────────────────────────────────────────────────────────

    public function test_leave_room_removes_player_and_broadcasts(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->leaveRoom($room->fresh(), $this->guest);

        $this->assertSame(0, $room->players()->where('user_id', $this->guest->id)->count());
        Event::assertDispatched(PlayerLeft::class);
    }

    public function test_leave_room_promotes_next_player_to_host(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->leaveRoom($room->fresh(), $this->host);

        $this->assertSame($this->guest->id, $room->fresh()->host_id);
    }

    public function test_leave_room_deletes_empty_room(): void
    {
        $room = $this->service->createRoom($this->host);
        $id = $room->id;

        $this->service->leaveRoom($room, $this->host);

        $this->assertNull(Room::find($id));
    }

    // ── startGame ────────────────────────────────────────────────────────────

    public function test_start_game_broadcasts_game_started_and_round_started(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->startGame($room->fresh());

        Event::assertDispatched(GameStarted::class);
        Event::assertDispatched(RoundStarted::class);
    }

    public function test_start_game_advances_to_round_1(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->startGame($room->fresh());

        $this->assertSame(1, $room->fresh()->current_round);
        $this->assertSame('question', $room->fresh()->status);
    }

    // ── submitAnswer ──────────────────────────────────────────────────────────

    public function test_submit_answer_stores_sanitized_and_broadcasts(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->guest, 'ROME!!!');

        $submission = $room->currentRoundSubmissions()->where('user_id', $this->guest->id)->first();

        $this->assertSame('rome', $submission->sanitized_answer);
        Event::assertDispatched(PlayerSubmitted::class);
    }

    public function test_submit_answer_rejects_empty_after_sanitization(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitAnswer($room->fresh(), $this->guest, '!!!');
    }

    public function test_submit_answer_rejects_duplicate_sanitized_answer(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $this->service->submitAnswer($room->fresh(), $this->host, 'london');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitAnswer($room->fresh(), $this->guest, 'LONDON!!!');
    }

    public function test_submit_answer_rejects_if_matches_correct_answer(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitAnswer($room->fresh(), $this->guest, 'PARIS!!!');
    }

    public function test_all_players_submitting_triggers_voting(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->host, 'berlin');
        $this->service->submitAnswer($room->fresh(), $this->guest, 'london');

        Event::assertDispatched(VotingStarted::class);
        $this->assertSame('voting', $room->fresh()->status);
    }

    // ── submitVote ────────────────────────────────────────────────────────────

    public function test_submit_vote_records_and_broadcasts_player_voted(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();

        $this->service->submitVote($room, $this->guest, $hostSub->id);

        Event::assertDispatched(PlayerVoted::class);
        $this->assertSame(1, $room->currentRoundVotes()->count());
    }

    public function test_submit_vote_rejects_voting_for_own_submission(): void
    {
        $room = $this->startVotingPhase();
        $guestSub = $room->currentRoundSubmissions()->where('user_id', $this->guest->id)->first();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitVote($room, $this->guest, $guestSub->id);
    }

    public function test_all_players_voting_triggers_reveal(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();

        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        Event::assertDispatched(RoundRevealed::class);
        $this->assertSame('reveal', $room->fresh()->status);
    }

    // ── scoring ───────────────────────────────────────────────────────────────

    public function test_scores_are_persisted_correctly_after_reveal(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();

        // guest votes for host's fake → host gets +1 (fooled guest)
        // host votes correct → host gets +2 (correct)
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        $hostScore = $room->players()->where('user_id', $this->host->id)->value('score');
        $guestScore = $room->players()->where('user_id', $this->guest->id)->value('score');

        $this->assertSame(3, $hostScore);
        $this->assertSame(0, $guestScore);
    }

    public function test_times_fooled_is_incremented_by_number_of_voters_fooled(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();

        // guest votes for host's fake → host fooled 1 player
        // host votes correct → guest fooled nobody
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        $hostTimesFooled = $room->players()->where('user_id', $this->host->id)->value('times_fooled');
        $guestTimesFooled = $room->players()->where('user_id', $this->guest->id)->value('times_fooled');

        $this->assertSame(1, $hostTimesFooled);
        $this->assertSame(0, $guestTimesFooled);
    }

    public function test_reveal_answers_correct_position_is_shuffled_not_always_last(): void
    {
        $room = $this->service->createRoom($this->host, 30);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        for ($i = 0; $i < 30; $i++) {
            $room = $room->fresh();
            $this->service->submitAnswer($room, $this->host, "host answer {$i}");
            $this->service->submitAnswer($room->fresh(), $this->guest, "guest answer {$i}");

            $room = $room->fresh();
            $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();
            $this->service->submitVote($room, $this->guest, $hostSub->id);
            $this->service->submitVote($room->fresh(), $this->host, null);

            $this->service->advanceAfterReveal($room->fresh());
        }

        $correctAnswerPositions = collect(Event::dispatched(RoundRevealed::class))
            ->map(fn ($dispatched) => collect($dispatched[0]->answers)->search(fn ($a) => $a['is_correct']))
            ->unique();

        $this->assertGreaterThan(1, $correctAnswerPositions->count());
    }

    // ── reveal auto-advance ──────────────────────────────────────────────────

    public function test_reveal_round_schedules_auto_advance_job(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();

        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        Queue::assertPushed(ExpireRevealPhase::class, function (ExpireRevealPhase $job) use ($room) {
            return $job->roomId === $room->id && $job->round === 1;
        });

        Event::assertDispatched(RoundRevealed::class, function (RoundRevealed $event) {
            return $event->timeLimit === RoomService::REVEAL_SECONDS;
        });
    }

    public function test_expire_reveal_phase_job_advances_to_next_round_when_still_pending(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        (new ExpireRevealPhase($room->id, 1))->handle($this->service);

        $this->assertSame(2, $room->fresh()->current_round);
        $this->assertSame('question', $room->fresh()->status);
    }

    public function test_expire_reveal_phase_job_ends_game_on_final_round(): void
    {
        $room = $this->service->createRoom($this->host, 1);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->host, 'berlin');
        $this->service->submitAnswer($room->fresh(), $this->guest, 'london');
        $room = $room->fresh();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        (new ExpireRevealPhase($room->id, 1))->handle($this->service);

        Event::assertDispatched(GameEnded::class);
        $this->assertSame('finished', $room->fresh()->status);
    }

    public function test_expire_reveal_phase_job_is_noop_if_host_already_advanced(): void
    {
        $room = $this->startVotingPhase();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        $this->service->advanceAfterReveal($room->fresh());
        $this->assertSame(2, $room->fresh()->current_round);

        // Stale job for round 1 should no-op since the room already moved on.
        (new ExpireRevealPhase($room->id, 1))->handle($this->service);

        $this->assertSame(2, $room->fresh()->current_round);
    }

    // ── spectator mode ───────────────────────────────────────────────────────

    public function test_host_can_enable_spectator_mode_while_waiting(): void
    {
        $room = $this->service->createRoom($this->host);

        $this->service->setSpectatorMode($room, $this->host, true);

        $this->assertTrue((bool) $room->players()->where('user_id', $this->host->id)->value('is_spectator'));
    }

    public function test_only_host_can_enable_spectator_mode(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->setSpectatorMode($room, $this->guest, true);
    }

    public function test_spectator_mode_cannot_change_after_game_started(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->setSpectatorMode($room->fresh(), $this->host, true);
    }

    public function test_spectating_host_is_excluded_from_answer_threshold(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->setSpectatorMode($room, $this->host, true);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->guest, 'london');

        // Only the guest is an active player, so a single submission should
        // already be enough to trigger voting.
        Event::assertDispatched(VotingStarted::class);
        $this->assertSame('voting', $room->fresh()->status);
    }

    public function test_spectator_cannot_submit_answer(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->setSpectatorMode($room, $this->host, true);
        $this->service->startGame($room->fresh());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitAnswer($room->fresh(), $this->host, 'berlin');
    }

    public function test_spectator_cannot_vote(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->setSpectatorMode($room, $this->host, true);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->guest, 'london');
        $room = $room->fresh();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitVote($room, $this->host, null);
    }

    public function test_spectating_host_is_excluded_from_leaderboard(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->setSpectatorMode($room, $this->host, true);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->guest, 'london');
        $room = $room->fresh();
        $this->service->submitVote($room, $this->guest, null);

        $this->assertSame(1, $room->activePlayers()->count());
        $this->assertSame(2, $room->players()->count());
    }

    // ── voting duration ──────────────────────────────────────────────────────

    public function test_voting_duration_scales_with_number_of_answer_choices(): void
    {
        $room = $this->startVotingPhase();

        Event::assertDispatched(VotingStarted::class, function (VotingStarted $event) {
            // 2 submissions (host + guest) + 1 correct answer = 3 choices.
            return $event->timeLimit === 3 * RoomService::VOTING_SECONDS_PER_CHOICE;
        });
    }

    // ── playAgain ─────────────────────────────────────────────────────────────

    public function test_play_again_creates_new_room_with_same_total_rounds(): void
    {
        $room = $this->finishGame(totalRounds: 3);

        $newRoom = $this->service->playAgain($room->fresh(), $this->host);

        $this->assertNotSame($room->code, $newRoom->code);
        $this->assertSame(3, $newRoom->total_rounds);
        $this->assertSame($this->host->id, $newRoom->host_id);
        $this->assertSame('waiting', $newRoom->status);
    }

    public function test_play_again_auto_joins_previous_players(): void
    {
        $room = $this->finishGame();

        $newRoom = $this->service->playAgain($room->fresh(), $this->host);

        $this->assertSame(2, $newRoom->players()->count());
        $this->assertTrue($newRoom->players()->where('user_id', $this->guest->id)->exists());
    }

    public function test_play_again_broadcasts_play_again_started(): void
    {
        $room = $this->finishGame();

        $newRoom = $this->service->playAgain($room->fresh(), $this->host);

        Event::assertDispatched(PlayAgainStarted::class, fn (PlayAgainStarted $event) => $event->roomCode === $room->code
            && $event->newRoomCode === $newRoom->code);
    }

    public function test_play_again_rejects_non_host(): void
    {
        $room = $this->finishGame();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->playAgain($room->fresh(), $this->guest);
    }

    public function test_play_again_rejects_if_game_not_finished(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->playAgain($room->fresh(), $this->host);
    }

    // ── startNextRound ────────────────────────────────────────────────────────

    public function test_start_next_round_stamps_asked_at_on_question(): void
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->startGame($room->fresh());

        $questionId = cache()->get("room:{$room->id}:round:1:question");
        $this->assertNotNull(Question::find($questionId)->asked_at);
    }

    public function test_start_next_round_avoids_questions_asked_this_week(): void
    {
        $askedThisWeek = Question::create([
            'body' => 'What is the capital of Germany?',
            'correct_answer' => 'berlin',
            'category' => 'Geography',
            'asked_at' => now(),
        ]);

        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->startGame($room->fresh());

        $questionId = cache()->get("room:{$room->id}:round:1:question");
        $this->assertNotSame($askedThisWeek->id, $questionId);
    }

    public function test_start_next_round_falls_back_when_all_questions_asked_this_week(): void
    {
        Question::query()->update(['asked_at' => now()]);

        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);

        $this->service->startGame($room->fresh());

        $questionId = cache()->get("room:{$room->id}:round:1:question");
        $this->assertNotNull($questionId);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function startVotingPhase(): Room
    {
        $room = $this->service->createRoom($this->host);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->host, 'berlin');
        $this->service->submitAnswer($room->fresh(), $this->guest, 'london');

        return $room->fresh();
    }

    private function finishGame(int $totalRounds = 1): Room
    {
        $room = $this->service->createRoom($this->host, $totalRounds);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        for ($round = 1; $round <= $totalRounds; $round++) {
            $room = $room->fresh();
            $this->service->submitAnswer($room, $this->host, "host answer {$round}");
            $this->service->submitAnswer($room->fresh(), $this->guest, "guest answer {$round}");

            $room = $room->fresh();
            $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();
            $this->service->submitVote($room, $this->guest, $hostSub->id);
            $this->service->submitVote($room->fresh(), $this->host, null);

            $this->service->advanceAfterReveal($room->fresh());
        }

        return $room->fresh();
    }
}
