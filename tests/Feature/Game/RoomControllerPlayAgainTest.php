<?php

namespace Tests\Feature\Game;

use App\Models\Question;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RoomControllerPlayAgainTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $service;

    private User $host;

    private User $guest;

    private Room $finishedRoom;

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

        $this->service->advanceAfterReveal($room->fresh());

        $this->finishedRoom = $room->fresh();
    }

    public function test_host_can_start_a_new_game_and_guest_is_auto_joined(): void
    {
        $response = $this->actingAs($this->host)
            ->postJson(route('rooms.play-again', $this->finishedRoom->code))
            ->assertOk()
            ->assertJsonStructure(['ok', 'code']);

        $newCode = $response->json('code');
        $this->assertNotSame($this->finishedRoom->code, $newCode);

        $newRoom = Room::where('code', $newCode)->firstOrFail();
        $this->assertSame($this->host->id, $newRoom->host_id);
        $this->assertTrue($newRoom->players()->where('user_id', $this->guest->id)->exists());
    }

    public function test_guest_cannot_start_a_new_game(): void
    {
        $this->actingAs($this->guest)
            ->postJson(route('rooms.play-again', $this->finishedRoom->code))
            ->assertForbidden();
    }
}
