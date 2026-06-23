import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router } from '@inertiajs/react';
import { Crown, Home } from 'lucide-react';

interface LeaderboardEntry {
    user_id: number;
    name: string;
    score: number;
}

interface Room {
    code: string;
}

interface Auth {
    user: { id: number; name: string };
}

export default function EndScreen({ room, leaderboard, auth }: { room: Room; leaderboard: LeaderboardEntry[]; auth: Auth }) {
    const winner = leaderboard[0];

    const playAgain = () => {
        router.post(route('rooms.store'), { total_rounds: 5 });
    };

    return (
        <>
            <Head title="Game Over" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-purple-600 to-indigo-800 p-4">
                <div className="w-full max-w-md space-y-6">
                    {/* Winner */}
                    {winner && (
                        <div className="text-center text-white">
                            <Crown className="mx-auto h-16 w-16 text-yellow-400" />
                            <h1 className="mt-2 text-3xl font-black">{winner.name} wins!</h1>
                            <p className="text-white/70">{winner.score} point{winner.score !== 1 ? 's' : ''}</p>
                        </div>
                    )}

                    {/* Leaderboard */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-center text-sm font-medium text-muted-foreground">Final Standings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-3">
                                {leaderboard.map((entry, i) => (
                                    <li key={entry.user_id} className="flex items-center gap-3">
                                        <span className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ${
                                            i === 0 ? 'bg-yellow-400 text-yellow-900' :
                                            i === 1 ? 'bg-gray-300 text-gray-700' :
                                            i === 2 ? 'bg-orange-400 text-orange-900' :
                                            'bg-muted text-muted-foreground'
                                        }`}>
                                            {i + 1}
                                        </span>
                                        <span className={`flex-1 font-medium ${entry.user_id === auth.user.id ? 'text-purple-600' : ''}`}>
                                            {entry.name}
                                            {entry.user_id === auth.user.id && ' (you)'}
                                        </span>
                                        <span className="font-bold">{entry.score} pt{entry.score !== 1 ? 's' : ''}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex gap-3">
                        <Button variant="outline" className="flex-1" onClick={() => router.visit(route('home'))}>
                            <Home className="mr-2 h-4 w-4" />
                            Home
                        </Button>
                        <Button className="flex-1 bg-purple-600 hover:bg-purple-700" onClick={playAgain}>
                            Play Again
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
