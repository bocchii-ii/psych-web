<?php

use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('room.{code}', function ($user, $code) {
    return Room::where('code', strtoupper($code))
        ->whereHas('players', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
