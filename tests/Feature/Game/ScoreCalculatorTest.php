<?php

namespace Tests\Feature\Game;

use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\RoundSubmission;
use App\Models\RoundVote;
use App\Models\User;
use App\Services\ScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ScoreCalculator $calculator;

    private User $host;

    private User $p2;

    private User $p3;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ScoreCalculator;
        $this->host = User::factory()->create();
        $this->p2 = User::factory()->create();
        $this->p3 = User::factory()->create();

        $this->room = Room::create([
            'code' => strtoupper(Str::random(6)),
            'host_id' => $this->host->id,
            'status' => 'reveal',
            'total_rounds' => 5,
            'current_round' => 1,
        ]);

        foreach ([$this->host->id, $this->p2->id, $this->p3->id] as $uid) {
            RoomPlayer::create(['room_id' => $this->room->id, 'user_id' => $uid, 'score' => 0]);
        }
    }

    public function test_player_gets_1_point_for_picking_correct_answer(): void
    {
        RoundSubmission::create([
            'room_id' => $this->room->id,
            'round_number' => 1,
            'user_id' => $this->p2->id,
            'raw_answer' => 'fake',
            'sanitized_answer' => 'fake',
        ]);

        RoundVote::create([
            'room_id' => $this->room->id,
            'round_number' => 1,
            'voter_id' => $this->host->id,
            'submission_id' => null,
        ]);

        $submissions = $this->room->currentRoundSubmissions()->get();
        $points = $this->calculator->calculate($this->room, $submissions, 'real answer');

        $this->assertSame(1, $points[$this->host->id]);
        $this->assertSame(0, $points[$this->p2->id]);
    }

    public function test_bluff_author_gets_1_point_per_player_fooled(): void
    {
        $s2 = RoundSubmission::create([
            'room_id' => $this->room->id,
            'round_number' => 1,
            'user_id' => $this->p2->id,
            'raw_answer' => 'bluff',
            'sanitized_answer' => 'bluff',
        ]);

        RoundVote::create(['room_id' => $this->room->id, 'round_number' => 1, 'voter_id' => $this->host->id, 'submission_id' => $s2->id]);
        RoundVote::create(['room_id' => $this->room->id, 'round_number' => 1, 'voter_id' => $this->p3->id, 'submission_id' => $s2->id]);

        $submissions = $this->room->currentRoundSubmissions()->get();
        $points = $this->calculator->calculate($this->room, $submissions, 'real answer');

        $this->assertSame(2, $points[$this->p2->id]);
        $this->assertSame(0, $points[$this->host->id]);
        $this->assertSame(0, $points[$this->p3->id]);
    }

    public function test_multiple_point_earners_in_same_round(): void
    {
        $s2 = RoundSubmission::create([
            'room_id' => $this->room->id,
            'round_number' => 1,
            'user_id' => $this->p2->id,
            'raw_answer' => 'bluff',
            'sanitized_answer' => 'bluff',
        ]);

        RoundVote::create(['room_id' => $this->room->id, 'round_number' => 1, 'voter_id' => $this->p3->id, 'submission_id' => null]);
        RoundVote::create(['room_id' => $this->room->id, 'round_number' => 1, 'voter_id' => $this->host->id, 'submission_id' => $s2->id]);

        $submissions = $this->room->currentRoundSubmissions()->get();
        $points = $this->calculator->calculate($this->room, $submissions, 'real answer');

        $this->assertSame(1, $points[$this->p3->id]);
        $this->assertSame(1, $points[$this->p2->id]);
        $this->assertSame(0, $points[$this->host->id]);
    }

    public function test_no_votes_yields_zero_points_for_everyone(): void
    {
        $submissions = $this->room->currentRoundSubmissions()->get();
        $points = $this->calculator->calculate($this->room, $submissions, 'real answer');

        $this->assertSame(0, $points[$this->host->id]);
        $this->assertSame(0, $points[$this->p2->id]);
        $this->assertSame(0, $points[$this->p3->id]);
    }
}
