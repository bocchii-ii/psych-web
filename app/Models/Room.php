<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'code',
        'host_id',
        'status',
        'total_rounds',
        'current_round',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(RoundSubmission::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(RoundVote::class);
    }

    public function currentRoundSubmissions(): HasMany
    {
        return $this->submissions()->where('round_number', $this->current_round);
    }

    public function currentRoundVotes(): HasMany
    {
        return $this->votes()->where('round_number', $this->current_round);
    }
}
