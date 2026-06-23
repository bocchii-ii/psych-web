<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundVote extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'round_number',
        'voter_id',
        'submission_id',
        'voted_at',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RoundSubmission::class, 'submission_id');
    }
}
