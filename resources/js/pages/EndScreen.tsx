import { ConfettiFireworks } from '@/components/magicui/confetti-fireworks';
import { SparklesText } from '@/components/magicui/sparkles-text';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Crown, Flame, Home, Snowflake } from 'lucide-react';
import { useEffect, useState } from 'react';

interface LeaderboardEntry {
    user_id: number;
    name: string;
    score: number;
}

interface Superlative {
    user_id: number;
    name: string;
    count: number;
}

interface Room {
    code: string;
    host_id: number;
}

interface Auth {
    user: { id: number; name: string };
}

export default function EndScreen({
    room,
    leaderboard,
    most_psyched: mostPsyched,
    least_psyched: leastPsyched,
    auth,
}: {
    room: Room;
    leaderboard: LeaderboardEntry[];
    most_psyched: Superlative | null;
    least_psyched: Superlative | null;
    auth: Auth;
}) {
    const winner = leaderboard[0];
    const isHost = auth.user.id === room.host_id;
    const [startingNewGame, setStartingNewGame] = useState(false);

    // The host's "Play Again" click creates a fresh room and everyone else
    // is auto-joined server-side; this broadcast tells their clients where
    // to go without them having to click anything themselves.
    useEffect(() => {
        const channel = window.Echo.private(`room.${room.code}`);

        channel.listen('.PlayAgainStarted', (e: { newRoomCode: string }) => {
            router.visit(route('rooms.lobby', e.newRoomCode));
        });

        return () => {
            channel.stopListening('.PlayAgainStarted');
        };
    }, [room.code]);

    const playAgain = async () => {
        setStartingNewGame(true);
        try {
            const { data } = await axios.post(route('rooms.play-again', room.code));
            router.visit(route('rooms.lobby', data.code));
        } catch {
            setStartingNewGame(false);
        }
    };

    return (
        <>
            <Head title="Game Over" />
            {winner && <ConfettiFireworks className="pointer-events-none fixed inset-0 z-50 size-full" />}
            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-purple-600 to-indigo-800 p-4">
                <div className="w-full max-w-md space-y-6">
                    {/* Winner */}
                    {winner && (
                        <div className="text-center text-white">
                            <Crown className="mx-auto h-16 w-16 text-yellow-400" />
                            <h1 className="mt-2 text-3xl font-black">
                                <SparklesText text={`${winner.name} wins!`} colors={{ first: '#FFD700', second: '#FFFFFF' }} />
                            </h1>
                            <p className="text-white/70">
                                {winner.score} point{winner.score !== 1 ? 's' : ''}
                            </p>
                        </div>
                    )}

                    {/* Leaderboard */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-center text-sm font-medium">Final Standings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-3">
                                {leaderboard.map((entry, i) => (
                                    <li key={entry.user_id} className="flex items-center gap-3">
                                        <span
                                            className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ${
                                                i === 0
                                                    ? 'bg-yellow-400 text-yellow-900'
                                                    : i === 1
                                                      ? 'bg-gray-300 text-gray-700'
                                                      : i === 2
                                                        ? 'bg-orange-400 text-orange-900'
                                                        : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {i + 1}
                                        </span>
                                        <span className={`flex-1 font-medium ${entry.user_id === auth.user.id ? 'text-purple-600' : ''}`}>
                                            {entry.name}
                                            {entry.user_id === auth.user.id && ' (you)'}
                                        </span>
                                        <span className="font-bold">
                                            {entry.score} pt{entry.score !== 1 ? 's' : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    {/* Superlatives */}
                    {(mostPsyched || leastPsyched) && (
                        <div className="grid grid-cols-2 gap-3">
                            {mostPsyched && (
                                <Card>
                                    <CardContent className="flex flex-col items-center gap-1 py-4 text-center">
                                        <Flame className="h-6 w-6 text-orange-500" />
                                        <p className="text-muted-foreground text-xs font-medium">Most Psyched</p>
                                        <p className="font-bold">{mostPsyched.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            fooled {mostPsyched.count} time{mostPsyched.count !== 1 ? 's' : ''}
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                            {leastPsyched && (
                                <Card>
                                    <CardContent className="flex flex-col items-center gap-1 py-4 text-center">
                                        <Snowflake className="h-6 w-6 text-blue-400" />
                                        <p className="text-muted-foreground text-xs font-medium">Least Psyched</p>
                                        <p className="font-bold">{leastPsyched.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            fooled {leastPsyched.count} time{leastPsyched.count !== 1 ? 's' : ''}
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex gap-3">
                        <Button variant="outline" className="flex-1" onClick={() => router.visit(route('home'))}>
                            <Home className="mr-2 h-4 w-4" />
                            Home
                        </Button>
                        {isHost ? (
                            <Button className="flex-1 bg-purple-600 hover:bg-purple-700" onClick={playAgain} disabled={startingNewGame}>
                                {startingNewGame ? 'Starting…' : 'Play Again'}
                            </Button>
                        ) : (
                            <div className="flex flex-1 items-center justify-center rounded-lg bg-white/10 text-sm text-white/70">
                                Waiting for host…
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
