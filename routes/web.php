<?php

use App\Http\Controllers\Auth\GuestSessionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [RoomController::class, 'index'])->name('home');

Route::post('/guest-login', [GuestSessionController::class, 'store'])->name('guest.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('home'))->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('/rooms/join', [RoomController::class, 'join'])->name('rooms.join');
    Route::get('/rooms/{code}/lobby', [RoomController::class, 'lobby'])->name('rooms.lobby');
    Route::post('/rooms/{code}/spectate', [RoomController::class, 'spectate'])->name('rooms.spectate');
    Route::post('/rooms/{code}/start', [RoomController::class, 'start'])->name('rooms.start');
    Route::get('/rooms/{code}/game', [RoomController::class, 'game'])->name('rooms.game');
    Route::delete('/rooms/{code}/leave', [RoomController::class, 'leave'])->name('rooms.leave');
    Route::get('/rooms/{code}/end', [GameController::class, 'endScreen'])->name('rooms.end');
    Route::post('/rooms/{code}/play-again', [RoomController::class, 'playAgain'])->name('rooms.play-again');

    Route::post('/rooms/{code}/submit', [GameController::class, 'submit'])->name('game.submit');
    Route::post('/rooms/{code}/vote', [GameController::class, 'vote'])->name('game.vote');
    Route::post('/rooms/{code}/next', [GameController::class, 'next'])->name('game.next');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
