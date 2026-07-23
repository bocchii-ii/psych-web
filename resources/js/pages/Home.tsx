import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, useForm } from '@inertiajs/react';
import { RefreshCw, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface OpenRoom {
    code: string;
    host_name: string;
    player_count: number;
    max_players: number;
    total_rounds: number;
    is_full: boolean;
    in_progress: boolean;
}

export default function Home({
    auth,
    categories = [],
    open_rooms: openRooms = [],
}: {
    auth?: { user: { id: number; name: string } | null };
    categories?: string[];
    open_rooms?: OpenRoom[];
}) {
    const [tab, setTab] = useState<'create' | 'join'>('create');
    const [refreshing, setRefreshing] = useState(false);

    const createForm = useForm({ total_rounds: '5', max_players: '8', excluded_categories: [] as string[] });
    const joinForm = useForm({ code: '' });
    const guestForm = useForm({ name: '' });

    const handleCreate: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('rooms.store'));
    };

    const handleJoin: FormEventHandler = (e) => {
        e.preventDefault();
        joinForm.post(route('rooms.join'));
    };

    const joinRoom = (code: string) => {
        router.post(route('rooms.join'), { code });
    };

    const refreshRooms = () => {
        setRefreshing(true);
        router.reload({ only: ['open_rooms'], onFinish: () => setRefreshing(false) });
    };

    const toggleCategory = (category: string) => {
        const current = createForm.data.excluded_categories;
        createForm.setData(
            'excluded_categories',
            current.includes(category) ? current.filter((c) => c !== category) : [...current, category],
        );
    };

    const handleGuestLogin: FormEventHandler = (e) => {
        e.preventDefault();
        guestForm.post(route('guest.login'));
    };

    const isAuthed = !!auth?.user;

    return (
        <>
            <Head title="Psych Web" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-purple-600 to-indigo-800 p-4">
                <div className="mb-8 text-center text-white">
                    <h1 className="text-5xl font-black tracking-tight">Psych!</h1>
                    <p className="mt-2 text-lg opacity-80">Outwit your friends with fake answers</p>
                </div>

                <Card className="w-full max-w-md shadow-2xl">
                    <CardHeader>
                        <div className="flex gap-2">
                            <button
                                onClick={() => setTab('create')}
                                className={`flex-1 rounded-lg py-2 text-sm font-semibold transition-colors ${
                                    tab === 'create'
                                        ? 'bg-purple-600 text-white'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                }`}
                            >
                                Create Room
                            </button>
                            <button
                                onClick={() => setTab('join')}
                                className={`flex-1 rounded-lg py-2 text-sm font-semibold transition-colors ${
                                    tab === 'join'
                                        ? 'bg-purple-600 text-white'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                }`}
                            >
                                Join Room
                            </button>
                        </div>
                    </CardHeader>

                    <CardContent>
                        {tab === 'create' ? (
                            <form onSubmit={handleCreate} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Number of Rounds</Label>
                                    <Select
                                        value={createForm.data.total_rounds}
                                        onValueChange={(v) => createForm.setData('total_rounds', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="3">3 rounds</SelectItem>
                                            <SelectItem value="5">5 rounds</SelectItem>
                                            <SelectItem value="7">7 rounds</SelectItem>
                                            <SelectItem value="10">10 rounds</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>Max Players</Label>
                                    <Select
                                        value={createForm.data.max_players}
                                        onValueChange={(v) => createForm.setData('max_players', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[2, 4, 6, 8, 10, 12, 16].map((n) => (
                                                <SelectItem key={n} value={String(n)}>
                                                    {n} players
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {createForm.errors.max_players && (
                                        <p className="text-sm text-red-500">{createForm.errors.max_players}</p>
                                    )}
                                </div>

                                {categories.length > 0 && (
                                    <div className="space-y-2">
                                        <Label>Exclude Categories</Label>
                                        <div className="grid max-h-40 grid-cols-2 gap-x-3 gap-y-2 overflow-y-auto rounded-lg border p-3">
                                            {categories.map((category) => (
                                                <label key={category} className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={createForm.data.excluded_categories.includes(category)}
                                                        onCheckedChange={() => toggleCategory(category)}
                                                    />
                                                    {category}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <Button type="submit" className="w-full bg-purple-600 hover:bg-purple-700" disabled={createForm.processing}>
                                    Create Room
                                </Button>
                                {createForm.errors.total_rounds && (
                                    <p className="text-sm text-red-500">{createForm.errors.total_rounds}</p>
                                )}
                            </form>
                        ) : (
                            <div className="space-y-4">
                                <form onSubmit={handleJoin} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Room Code</Label>
                                        <Input
                                            value={joinForm.data.code}
                                            onChange={(e) => joinForm.setData('code', e.target.value.toUpperCase())}
                                            placeholder="e.g. XKQP7Z"
                                            maxLength={8}
                                            className="text-center text-xl font-bold uppercase tracking-widest"
                                        />
                                    </div>
                                    <Button type="submit" className="w-full bg-purple-600 hover:bg-purple-700" disabled={joinForm.processing}>
                                        Join Room
                                    </Button>
                                    {joinForm.errors.code && (
                                        <p className="text-sm text-red-500">{joinForm.errors.code}</p>
                                    )}
                                </form>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label>Open Lobbies</Label>
                                        <button
                                            type="button"
                                            onClick={refreshRooms}
                                            className="text-muted-foreground hover:text-foreground"
                                            aria-label="Refresh lobby list"
                                        >
                                            <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                                        </button>
                                    </div>
                                    {openRooms.length === 0 ? (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            No open lobbies right now — create one!
                                        </p>
                                    ) : (
                                        <ul className="max-h-64 space-y-2 overflow-y-auto">
                                            {openRooms.map((r) => (
                                                <li
                                                    key={r.code}
                                                    className="flex items-center justify-between gap-2 rounded-lg bg-muted px-3 py-2"
                                                >
                                                    <div>
                                                        <p className="flex items-center gap-2 text-sm font-semibold">
                                                            {r.host_name}'s room
                                                            {r.in_progress && (
                                                                <span className="rounded-full bg-red-500/90 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white">
                                                                    LIVE
                                                                </span>
                                                            )}
                                                        </p>
                                                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                                            <Users className="h-3 w-3" />
                                                            {r.player_count}/{r.max_players} · {r.total_rounds} rounds
                                                        </p>
                                                    </div>
                                                    <Button
                                                        size="sm"
                                                        className="bg-purple-600 hover:bg-purple-700"
                                                        disabled={(!r.in_progress && r.is_full) || joinForm.processing}
                                                        onClick={() => joinRoom(r.code)}
                                                    >
                                                        {r.in_progress ? 'Spectate' : r.is_full ? 'Full' : 'Join'}
                                                    </Button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {isAuthed ? (
                    <p className="mt-6 text-sm text-white/60">
                        Playing as <span className="font-semibold text-white">{auth!.user!.name}</span>
                    </p>
                ) : (
                    <>
                        <Card className="mt-6 w-full max-w-md shadow-2xl">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Play as Guest</CardTitle>
                                <CardDescription>Just pick a name — no account needed</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleGuestLogin} className="flex gap-2">
                                    <Input
                                        value={guestForm.data.name}
                                        onChange={(e) => guestForm.setData('name', e.target.value)}
                                        placeholder="Your in-game name…"
                                        maxLength={255}
                                    />
                                    <Button
                                        type="submit"
                                        className="shrink-0 bg-purple-600 hover:bg-purple-700"
                                        disabled={guestForm.processing || !guestForm.data.name.trim()}
                                    >
                                        Play
                                    </Button>
                                </form>
                                {guestForm.errors.name && (
                                    <p className="mt-2 text-sm text-red-500">{guestForm.errors.name}</p>
                                )}
                            </CardContent>
                        </Card>

                        <p className="mt-6 text-sm text-white/60">
                            <a href={route('login')} className="underline">Sign in</a> or <a href={route('register')} className="underline">register</a> to play
                        </p>
                    </>
                )}
            </div>
        </>
    );
}
