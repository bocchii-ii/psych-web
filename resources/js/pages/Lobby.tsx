import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router } from '@inertiajs/react';
import { Copy, LogOut, Play, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Player {
    id: number;
    name: string;
}

interface Room {
    id: number;
    code: string;
    total_rounds: number;
    host_id: number;
    status: string;
}

interface Auth {
    user: { id: number; name: string };
}

interface Props {
    room: Room;
    players: Player[];
    auth: Auth;
}

export default function Lobby({ room, players: initialPlayers, auth }: Props) {
    const [players, setPlayers] = useState<Player[]>(initialPlayers);
    const [copied, setCopied] = useState(false);
    const isHost = auth.user.id === room.host_id;

    useEffect(() => {
        const channel = window.Echo.private(`room.${room.code}`);

        channel
            .listen('.PlayerJoined', (e: { userId: number; playerName: string; playerCount: number }) => {
                setPlayers((prev) => {
                    if (prev.some((p) => p.id === e.userId)) return prev;
                    return [...prev, { id: e.userId, name: e.playerName }];
                });
            })
            .listen('.PlayerLeft', (e: { userId: number }) => {
                setPlayers((prev) => prev.filter((p) => p.id !== e.userId));
            })
            .listen('.GameStarted', () => {
                router.visit(route('rooms.game', room.code));
            });

        return () => {
            channel.stopListening('.PlayerJoined');
            channel.stopListening('.PlayerLeft');
            channel.stopListening('.GameStarted');
        };
    }, [room.code]);

    const copyCode = () => {
        navigator.clipboard.writeText(room.code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const startGame = () => {
        router.post(route('rooms.start', room.code));
    };

    const leave = () => {
        router.delete(route('rooms.leave', room.code));
    };

    return (
        <>
            <Head title={`Lobby — ${room.code}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-purple-600 to-indigo-800 p-4">
                <div className="w-full max-w-md space-y-4">
                    {/* Invite code */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-center text-sm font-medium text-muted-foreground">Room Code</CardTitle>
                        </CardHeader>
                        <CardContent className="text-center">
                            <div className="text-5xl font-black tracking-widest text-purple-600">{room.code}</div>
                            <Button variant="outline" size="sm" className="mt-3" onClick={copyCode}>
                                <Copy className="mr-2 h-4 w-4" />
                                {copied ? 'Copied!' : 'Copy Code'}
                            </Button>
                        </CardContent>
                    </Card>

                    {/* Players list */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Users className="h-4 w-4" />
                                Players ({players.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {players.map((p) => (
                                    <li key={p.id} className="flex items-center gap-2 rounded-lg bg-muted px-3 py-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-600 text-sm font-bold text-white">
                                            {p.name[0].toUpperCase()}
                                        </div>
                                        <span className="font-medium">{p.name}</span>
                                        {p.id === room.host_id && (
                                            <span className="ml-auto text-xs font-semibold text-purple-600">HOST</span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    {/* Info */}
                    <p className="text-center text-sm text-white/70">{room.total_rounds} rounds</p>

                    {/* Actions */}
                    <div className="flex gap-2">
                        <Button variant="outline" className="flex-1" onClick={leave}>
                            <LogOut className="mr-2 h-4 w-4" />
                            Leave
                        </Button>
                        {isHost && (
                            <Button
                                className="flex-1 bg-green-500 hover:bg-green-600"
                                onClick={startGame}
                                disabled={players.length < 2}
                            >
                                <Play className="mr-2 h-4 w-4" />
                                Start Game
                            </Button>
                        )}
                        {!isHost && (
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
