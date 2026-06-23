<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundRevealed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $correctAnswer
     * @param array<int, array{id: int|null, text: string, author: string|null, voters: string[], points_earned: int}> $answers
     * @param array<int, array{user_id: int, name: string, score: int, delta: int}> $leaderboard
     */
    public function __construct(
        public string $roomCode,
        public string $correctAnswer,
        public array $answers,
        public array $leaderboard,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("room.{$this->roomCode}")];
    }

    public function broadcastAs(): string
    {
        return 'RoundRevealed';
    }
}
