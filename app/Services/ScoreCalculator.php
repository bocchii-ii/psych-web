<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoundSubmission;
use Illuminate\Support\Collection;

class ScoreCalculator
{
    /**
     * Returns [user_id => points_earned_this_round]
     *
     * @param  Collection<int, RoundSubmission>  $submissions
     * @return array<int, int>
     */
    public function calculate(Room $room, Collection $submissions, string $correctAnswer): array
    {
        $playerIds = $room->players()->pluck('user_id');
        $points = $playerIds->mapWithKeys(fn ($id) => [$id => 0])->all();

        $votes = $room->currentRoundVotes()->with('voter')->get();

        foreach ($votes as $vote) {
            if ($vote->submission_id === null) {
                // voted for the correct answer
                $points[$vote->voter_id] = ($points[$vote->voter_id] ?? 0) + 2;
            } else {
                // voter was fooled — reward the submission author
                $submission = $submissions->firstWhere('id', $vote->submission_id);
                if ($submission) {
                    $points[$submission->user_id] = ($points[$submission->user_id] ?? 0) + 1;
                }
            }
        }

        return $points;
    }
}
