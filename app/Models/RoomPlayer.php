<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomPlayer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'user_id',
        'score',
        'times_fooled',
        'times_gullible',
        'joined_at',
        'is_spectator',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'is_spectator' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
