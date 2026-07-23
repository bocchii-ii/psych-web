<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function __construct(private RoomService $roomService) {}

    public function submit(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'answer' => 'required|string|max:255',
        ]);

        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $this->roomService->submitAnswer($room, $request->user(), $data['answer']);

        return response()->json(['ok' => true]);
    }

    public function vote(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'submission_id' => 'nullable|integer',
        ]);

        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $this->roomService->submitVote($room, $request->user(), $data['submission_id']);

        return response()->json(['ok' => true]);
    }

    public function next(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        abort_if($room->host_id !== $request->user()->id, 403);
        abort_if($room->status !== 'reveal', 422, 'Not in reveal phase.');

        $this->roomService->advanceAfterReveal($room);

        return response()->json(['ok' => true]);
    }

    public function endScreen(string $code): Response
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $players = $room->activePlayers()->with('user')->get();

        $leaderboard = $players->sortByDesc('score')->values()
            ->map(fn ($rp) => [
                'user_id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
            ]);

        $psychedMost = $players->sortByDesc('times_fooled')->first();
        $mostGullible = $players->sortByDesc('times_gullible')->first();

        return Inertia::render('EndScreen', [
            'room' => ['code' => $room->code, 'host_id' => $room->host_id],
            'leaderboard' => $leaderboard,
            'psyched_most' => $psychedMost ? [
                'user_id' => $psychedMost->user_id,
                'name' => $psychedMost->user->name,
                'count' => $psychedMost->times_fooled,
            ] : null,
            'most_gullible' => $mostGullible ? [
                'user_id' => $mostGullible->user_id,
                'name' => $mostGullible->user->name,
                'count' => $mostGullible->times_gullible,
            ] : null,
            'auth' => ['user' => auth()->user()->only('id', 'name')],
        ]);
    }
}
