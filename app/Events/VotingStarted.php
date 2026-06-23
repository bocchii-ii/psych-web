<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<int, array{id: int|null, text: string, is_correct: bool}> $answers
     *   id is null for the correct answer entry
     */
    public function __construct(
        public string $roomCode,
        public array $answers,
        public int $timeLimit,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("room.{$this->roomCode}")];
    }

    public function broadcastAs(): string
    {
        return 'VotingStarted';
    }
}
