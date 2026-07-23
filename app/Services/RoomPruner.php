<?php

namespace App\Services;

use App\Models\Room;

class RoomPruner
{
    public const INACTIVE_MINUTES = 30;

    /**
     * Delete rooms that are no longer worth keeping around: nobody left in
     * them at all, a started game with nobody actually still playing (just
     * spectators, or a leftover row), or anything that hasn't progressed in
     * INACTIVE_MINUTES (an abandoned lobby, or a game stuck because no queue
     * worker was running to fire its expire jobs).
     *
     * @return int number of rooms deleted
     */
    public function prune(): int
    {
        $staleThreshold = now()->subMinutes(self::INACTIVE_MINUTES);

        $rooms = Room::query()
            ->withCount(['players', 'activePlayers'])
            ->get()
            ->filter(function (Room $room) use ($staleThreshold) {
                if ($room->players_count === 0) {
                    return true;
                }

                if ($room->status !== 'waiting' && $room->active_players_count === 0) {
                    return true;
                }

                return $room->updated_at->lt($staleThreshold);
            });

        foreach ($rooms as $room) {
            $room->delete();
        }

        return $rooms->count();
    }
}
