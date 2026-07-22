<?php

namespace App\Jobs;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireRevealPhase implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $roomId,
        public int $round,
    ) {}

    public function handle(RoomService $roomService): void
    {
        $room = Room::find($this->roomId);

        if (! $room || $room->current_round !== $this->round || $room->status !== 'reveal') {
            return;
        }

        $roomService->advanceAfterReveal($room);
    }
}
