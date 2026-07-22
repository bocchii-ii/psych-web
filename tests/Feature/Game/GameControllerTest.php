<?php

namespace Tests\Feature\Game;

use App\Models\Question;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameControllerTest extends TestCase
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

    public function test_end_screen_shows_winner_and_psych_superlatives(): void
    {
        $room = $this->service->createRoom($this->host, 1);
        $this->service->joinRoom($room->code, $this->guest);
        $this->service->startGame($room->fresh());

        $room = $room->fresh();
        $this->service->submitAnswer($room, $this->host, 'berlin');
        $this->service->submitAnswer($room->fresh(), $this->guest, 'london');

        $room = $room->fresh();
        $hostSub = $room->currentRoundSubmissions()->where('user_id', $this->host->id)->first();

        // guest gets fooled by host's fake answer, host picks the correct one.
        $this->service->submitVote($room, $this->guest, $hostSub->id);
        $this->service->submitVote($room->fresh(), $this->host, null);

        $this->service->advanceAfterReveal($room->fresh());

        $this->actingAs($this->host)
            ->get(route('rooms.end', $room->code))
            ->assertInertia(fn (Assert $page) => $page
                ->component('EndScreen')
                ->where('leaderboard.0.user_id', $this->host->id)
                ->where('most_psyched.user_id', $this->host->id)
                ->where('most_psyched.count', 1)
                ->where('least_psyched.user_id', $this->guest->id)
                ->where('least_psyched.count', 0)
            );
    }
}
