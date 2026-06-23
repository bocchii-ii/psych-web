# Psych Web — Game Plan

A browser-based multiplayer trivia bluffing game inspired by "Psych! Outwit Your Friends".
Players submit fake answers to trivia questions, then vote on which answer is the real one.
Points are earned by answering correctly or by fooling other players with your fake answer.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 |
| WebSockets | Laravel Reverb + Laravel Echo |
| Frontend | ReactJS via Inertia.js |
| Styling | Tailwind CSS |
| Database | MySQL |
| Queue | Laravel Queues (database or Redis) |

---

## Game Flow

```
Home
 ├── Create Room → Lobby (host)
 └── Join Room (code) → Lobby (guest)

Lobby
 └── Host: set rounds → Start Game

  ┌─── For each round ───────────────────────────────────┐
  │                                                        │
  │  [QUESTION PHASE]  60s timer                          │
  │  Random trivia question shown                         │
  │  Each player secretly submits a fake answer           │
  │  (Host gets a "REAL ANSWER" badge, still submits)     │
  │                                                        │
  │  [VOTING PHASE]                                        │
  │  All collected answers + the real answer displayed    │
  │  Players pick which one they believe is correct       │
  │  (You cannot vote for your own answer)                │
  │                                                        │
  │  [REVEAL PHASE]                                        │
  │  Correct answer highlighted                           │
  │  Who-picked-what shown per answer                     │
  │  Points awarded, round leaderboard shown              │
  │                                                        │
  └────────────────────────────────────────────────────────┘

End Screen
 └── Final scores, winner crown, play again / leave
```

---

## Scoring

| Action | Points |
|---|---|
| Picking the correct answer | +1 |
| Each player fooled by your fake answer | +1 per player |

---

## Answer Sanitization Rules

Applied server-side before storing and before display:

1. Trim whitespace
2. Convert to lowercase
3. Strip all characters except `[a-z0-9 ]` (letters, digits, spaces)
4. Collapse multiple consecutive spaces into one

Example: `"Mount Éverest!!"` → `"mount verest"` (accented chars stripped)
Example: `"K2 (8,611 m)"` → `"k2 8611 m"`

Duplicate detection: after sanitization, if a player's answer matches another player's sanitized answer, the later submission is rejected with a prompt to try again.

---

## Database Schema

### `users`
Standard Laravel auth table.

### `rooms`
```
id              bigint PK
code            string(8)  UNIQUE  — invite code e.g. "XKQP7Z"
host_id         FK users
status          enum: waiting | question | voting | reveal | finished
total_rounds    tinyint   default 5
current_round   tinyint   default 0
created_at / updated_at
```

### `room_players`
```
id
room_id         FK rooms
user_id         FK users
score           int  default 0
is_ready        boolean default false
joined_at       timestamp
```

### `questions`
```
id
body            text       — the question text
correct_answer  string     — stored pre-sanitized (lowercase, clean)
category        string nullable
created_at
```
Seed with a large question bank (Open Trivia DB import or manual).

### `round_submissions`
```
id
room_id         FK rooms
round_number    tinyint
user_id         FK users
raw_answer      string     — original input preserved for display
sanitized_answer string    — processed version used for comparison
submitted_at    timestamp
```

### `round_votes`
```
id
room_id         FK rooms
round_number    tinyint
voter_id        FK users
submission_id   FK round_submissions nullable  — null = voted for correct answer
voted_at        timestamp
```

---

## WebSocket Channels & Events

All events broadcast on a **private** channel: `room.{room_code}`

### Outbound Events (Server → Clients)

| Event | Payload | Trigger |
|---|---|---|
| `PlayerJoined` | player name, avatar, player count | user joins room |
| `PlayerLeft` | player name | user disconnects |
| `GameStarted` | total_rounds | host starts game |
| `RoundStarted` | round_number, question body, time_limit | next round begins |
| `PlayerSubmitted` | player_name (no answer revealed) | any player submits |
| `VotingStarted` | shuffled list of sanitized answers + answer IDs | all players submitted or timer expired |
| `PlayerVoted` | voter name (no choice revealed) | any player votes |
| `RoundRevealed` | correct answer, per-answer voter lists, point deltas, cumulative scores | all players voted or timer expired |
| `GameEnded` | final leaderboard | last round revealed |

### Inbound (Client → Server via HTTP, not sockets)

All player actions go through standard Laravel HTTP controllers.
Reverb is used only for broadcasting state changes outward.

---

## Routes

```
GET  /                          → Home (create or join)
POST /rooms                     → create room
POST /rooms/join                → join by code
GET  /rooms/{code}/lobby        → Lobby page
POST /rooms/{code}/start        → host starts game
POST /rooms/{code}/submit       → player submits answer
POST /rooms/{code}/vote         → player votes
GET  /rooms/{code}/game         → Game page (SSR via Inertia)
DELETE /rooms/{code}/leave      → leave room
```

---

## Laravel Services & Jobs

### `RoomService`
- `createRoom(User $host): Room`
- `joinRoom(string $code, User $user): RoomPlayer`
- `startGame(Room $room): void`
- `advanceRound(Room $room): void`
- `finalizeRound(Room $room): void`
- `endGame(Room $room): void`

### `AnswerSanitizer`
```php
class AnswerSanitizer
{
    public function sanitize(string $raw): string
    {
        $lower = mb_strtolower(trim($raw));
        $ascii = preg_replace('/[^a-z0-9 ]/u', '', $lower);
        return preg_replace('/\s+/', ' ', $ascii);
    }
}
```

### `ScoreCalculator`
- Accepts the round's submissions and votes
- Returns array of `[user_id => points_earned_this_round]`

### Jobs
- `ExpireAnswerPhase` — dispatched with 60s delay when round starts; triggers `VotingStarted` if not all submitted
- `ExpireVotingPhase` — dispatched with 30s delay when voting starts; triggers `RoundRevealed` if not all voted

---

## Frontend Pages (React + Inertia)

### `Home.jsx`
- "Create Room" button → POST `/rooms`
- "Join Room" form (code input) → POST `/rooms/join`

### `Lobby.jsx`
Props: `room`, `players`, `isHost`

- Displays invite code prominently with copy button
- Live player list (updated via Echo `PlayerJoined` / `PlayerLeft`)
- Host: round count selector (3 / 5 / 7 / 10), "Start Game" button
- Guests: "Waiting for host…" state

### `Game.jsx`
Handles all in-game phases via local state driven by Echo events.

**Phase: `question`**
- Question text displayed large
- Countdown timer (60s, animated ring)
- Text input for fake answer
- Submit button (disabled after submit)
- Submitted player avatars shown as greyed silhouettes that fill in as they submit

**Phase: `voting`**
- Same question shown
- Answer cards displayed in a grid (shuffled order)
- Each card shows the sanitized answer text
- Click to select, confirm button
- Cannot select own answer (card is greyed out)
- Timer (30s)

**Phase: `reveal`**
- Correct answer card glows green
- Each answer card flips to show which players voted for it
- Point gains float up on each player avatar
- Cumulative round leaderboard shown
- "Next Round" button (host only) or auto-advance after 8s

### `EndScreen.jsx`
- Final leaderboard with winner crown animation
- "Play Again" (resets room to lobby) / "Leave" buttons

---

## Echo Setup (Frontend)

```js
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
```

Usage inside a React component:
```js
useEffect(() => {
    const channel = window.Echo.private(`room.${roomCode}`);

    channel
        .listen('RoundStarted', (e) => setPhase('question'))
        .listen('PlayerSubmitted', (e) => markSubmitted(e.playerName))
        .listen('VotingStarted', (e) => { setAnswers(e.answers); setPhase('voting'); })
        .listen('PlayerVoted', (e) => markVoted(e.playerName))
        .listen('RoundRevealed', (e) => { setReveal(e); setPhase('reveal'); })
        .listen('GameEnded', (e) => router.visit('/end', { data: e }));

    return () => channel.stopListening();
}, [roomCode]);
```

---

## Reverb Configuration

```env
REVERB_APP_ID=psych_web
REVERB_APP_KEY=your_key
REVERB_APP_SECRET=your_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

BROADCAST_CONNECTION=reverb
```

Start Reverb: `php artisan reverb:start`

---

## Key Edge Cases

| Scenario | Handling |
|---|---|
| Player disconnects mid-game | Room continues; disconnected player's slot is skipped for submissions/votes. `PlayerLeft` event broadcast. Host can kick. |
| Only one player submits before timer | Their answer + correct answer shown; they must vote between the two |
| Duplicate sanitized answers | Server rejects with 422 and asks player to rephrase |
| Host disconnects | First joined player is promoted to host |
| All players submit before 60s | Timer cancels, voting phase starts immediately |
| Player submits after phase ends | 422 rejected; phase already closed |

---

## Implementation Order

1. **Auth** — Laravel Breeze (React + Inertia preset)
2. **DB Migrations** — rooms, room_players, questions, round_submissions, round_votes
3. **Question Seeder** — import 200+ trivia questions
4. **Room CRUD** — create, join, leave, lobby page
5. **Echo + Reverb** — install, configure, verify connection
6. **Game State Machine** — RoomService, phase transitions, jobs
7. **Answer Submission** — AnswerSanitizer, duplicate detection, broadcast PlayerSubmitted
8. **Voting** — VotingStarted broadcast, vote recording, broadcast PlayerVoted
9. **Reveal + Scoring** — ScoreCalculator, RoundRevealed broadcast, score persistence
10. **End Game** — GameEnded broadcast, EndScreen page
11. **UI Polish** — timers, animations, mobile responsiveness
12. **Edge Cases** — disconnect handling, host promotion, timeout jobs

---

## Directory Structure (key files)

```
app/
  Broadcasting/
    PlayerJoined.php
    PlayerLeft.php
    GameStarted.php
    RoundStarted.php
    PlayerSubmitted.php
    VotingStarted.php
    PlayerVoted.php
    RoundRevealed.php
    GameEnded.php
  Http/Controllers/
    RoomController.php
    GameController.php
  Jobs/
    ExpireAnswerPhase.php
    ExpireVotingPhase.php
  Services/
    RoomService.php
    AnswerSanitizer.php
    ScoreCalculator.php
  Models/
    Room.php
    RoomPlayer.php
    Question.php
    RoundSubmission.php
    RoundVote.php

resources/js/
  Pages/
    Home.jsx
    Lobby.jsx
    Game.jsx
    EndScreen.jsx
  Components/
    AnswerCard.jsx
    PlayerAvatar.jsx
    CountdownRing.jsx
    Leaderboard.jsx
    InviteCode.jsx
```
