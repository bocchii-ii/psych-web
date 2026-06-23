<?php

namespace Tests\Feature\Game;

use App\Events\GameStarted;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\PlayerSubmitted;
use App\Events\PlayerVoted;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Events\VotingStarted;
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
        // host votes correct → host gets +1 (correct)
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        $hostScore = $room->players()->where('user_id', $this->host->id)->value('score');
        $guestScore = $room->players()->where('user_id', $this->guest->id)->value('score');

        $this->assertSame(2, $hostScore);
        $this->assertSame(0, $guestScore);
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
}
