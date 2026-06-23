<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(private RoomService $roomService) {}

    public function index(): Response
    {
        return Inertia::render('Home');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'total_rounds' => 'required|integer|in:3,5,7,10',
        ]);

        $room = $this->roomService->createRoom($request->user(), $data['total_rounds']);

        return redirect()->route('rooms.lobby', $room->code);
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|min:6|max:8',
        ]);

        $room = $this->roomService->joinRoom($data['code'], $request->user());

        return redirect()->route('rooms.lobby', $room->code);
    }

    public function lobby(string $code): Response
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        // Redirect if game already started
        if ($room->status !== 'waiting') {
            return Inertia::render('Game', $this->gameProps($room));
        }

        $players = $room->players()->with('user')->orderBy('joined_at')->get()
            ->map(fn ($rp) => [
                'id' => $rp->user_id,
                'name' => $rp->user->name,
            ]);

        return Inertia::render('Lobby', [
            'room' => [
                'id' => $room->id,
                'code' => $room->code,
                'total_rounds' => $room->total_rounds,
                'host_id' => $room->host_id,
                'status' => $room->status,
            ],
            'players' => $players,
            'auth' => ['user' => auth()->user()->only('id', 'name')],
        ]);
    }

    public function start(Request $request, string $code): RedirectResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        abort_if($room->host_id !== $request->user()->id, 403);
        abort_if($room->status !== 'waiting', 422, 'Game already started.');
        abort_if($room->players()->count() < 2, 422, 'Need at least 2 players to start.');

        $this->roomService->startGame($room);

        return redirect()->route('rooms.game', $room->code);
    }

    public function game(string $code): Response
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        return Inertia::render('Game', $this->gameProps($room));
    }

    public function leave(Request $request, string $code): RedirectResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();
        $this->roomService->leaveRoom($room, $request->user());

        return redirect()->route('home');
    }

    private function gameProps(Room $room): array
    {
        $players = $room->players()->with('user')->orderBy('joined_at')->get()
            ->map(fn ($rp) => [
                'id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
            ]);

        return [
            'room' => [
                'id' => $room->id,
                'code' => $room->code,
                'status' => $room->status,
                'total_rounds' => $room->total_rounds,
                'current_round' => $room->current_round,
                'host_id' => $room->host_id,
            ],
            'players' => $players,
            'auth' => ['user' => auth()->user()->only('id', 'name')],
        ];
    }
}
