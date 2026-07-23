<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Room;
use App\Services\RoomPruner;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(
        private RoomService $roomService,
        private RoomPruner $roomPruner,
    ) {}

    public function index(): Response
    {
        // Opportunistic cleanup: the scheduler-driven `rooms:prune` command is
        // the primary mechanism, but a home-page visit is a free chance to
        // keep the open-lobby list free of dead rooms even on setups where
        // the scheduler cron isn't wired up.
        $this->roomPruner->prune();

        $categories = Question::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $openRooms = Room::whereIn('status', ['waiting', 'question', 'voting', 'reveal'])
            ->withCount('players')
            ->with('host')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Room $room) => [
                'code' => $room->code,
                'host_name' => $room->host->name,
                'player_count' => $room->players_count,
                'max_players' => $room->max_players,
                'total_rounds' => $room->total_rounds,
                'is_full' => $room->status === 'waiting' && $room->players_count >= $room->max_players,
                'in_progress' => $room->status !== 'waiting',
            ]);

        return Inertia::render('Home', [
            'categories' => $categories,
            'open_rooms' => $openRooms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $categories = Question::query()->whereNotNull('category')->distinct()->pluck('category');

        $data = $request->validate([
            'total_rounds' => 'required|integer|in:3,5,7,10',
            'max_players' => 'required|integer|min:2|max:16',
            'excluded_categories' => 'array',
            'excluded_categories.*' => ['string', Rule::in($categories)],
        ]);

        $room = $this->roomService->createRoom(
            $request->user(),
            $data['total_rounds'],
            $data['max_players'],
            $data['excluded_categories'] ?? [],
        );

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
                'is_spectator' => $rp->is_spectator,
            ]);

        return Inertia::render('Lobby', [
            'room' => [
                'id' => $room->id,
                'code' => $room->code,
                'total_rounds' => $room->total_rounds,
                'host_id' => $room->host_id,
                'status' => $room->status,
                'max_players' => $room->max_players,
                'excluded_categories' => $room->excluded_categories ?? [],
            ],
            'players' => $players,
            'auth' => ['user' => auth()->user()->only('id', 'name')],
        ]);
    }

    public function spectate(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'is_spectator' => 'required|boolean',
        ]);

        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $this->roomService->setSpectatorMode($room, $request->user(), $data['is_spectator']);

        return response()->json(['ok' => true]);
    }

    public function start(Request $request, string $code): RedirectResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        abort_if($room->host_id !== $request->user()->id, 403);
        abort_if($room->status !== 'waiting', 422, 'Game already started.');
        abort_if($room->activePlayers()->count() < 2, 422, 'Need at least 2 players to start.');

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

    public function playAgain(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $newRoom = $this->roomService->playAgain($room, $request->user());

        return response()->json(['ok' => true, 'code' => $newRoom->code]);
    }

    private function gameProps(Room $room): array
    {
        $players = $room->activePlayers()->with('user')->orderBy('joined_at')->get()
            ->map(fn ($rp) => [
                'id' => $rp->user_id,
                'name' => $rp->user->name,
                'score' => $rp->score,
            ]);

        $isSpectator = (bool) $room->players()->where('user_id', auth()->id())->value('is_spectator');

        $question = null;
        $timeLeft = 0;
        $answers = [];
        $votedSubmissionId = null;
        $hasVoted = false;

        if (in_array($room->status, ['question', 'voting', 'reveal'], true)) {
            $question = cache()->get("room:{$room->id}:round:{$room->current_round}:question_body");
        }

        if ($room->status === 'question') {
            $deadline = cache()->get("room:{$room->id}:round:{$room->current_round}:deadline");
            if ($deadline) {
                $timeLeft = max(0, Carbon::parse($deadline)->getTimestamp() - now()->getTimestamp());
            }
        }

        if ($room->status === 'voting') {
            $answers = cache()->get("room:{$room->id}:round:{$room->current_round}:answers", []);
            $deadline = cache()->get("room:{$room->id}:round:{$room->current_round}:voting_deadline");
            if ($deadline) {
                $timeLeft = max(0, Carbon::parse($deadline)->getTimestamp() - now()->getTimestamp());
            }

            $existingVote = $room->currentRoundVotes()->where('voter_id', auth()->id())->first();
            if ($existingVote) {
                $hasVoted = true;
                $votedSubmissionId = $existingVote->submission_id;
            }
        }

        if ($room->status === 'reveal') {
            $deadline = cache()->get("room:{$room->id}:round:{$room->current_round}:reveal_deadline");
            if ($deadline) {
                $timeLeft = max(0, Carbon::parse($deadline)->getTimestamp() - now()->getTimestamp());
            }
        }

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
            'question' => $question,
            'time_left' => $timeLeft,
            'is_spectator' => $isSpectator,
            'answers' => $answers,
            'has_voted' => $hasVoted,
            'voted_submission_id' => $votedSubmissionId,
        ];
    }
}
