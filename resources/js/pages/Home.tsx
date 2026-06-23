import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Home() {
    const [tab, setTab] = useState<'create' | 'join'>('create');

    const createForm = useForm({ total_rounds: '5' });
    const joinForm = useForm({ code: '' });

    const handleCreate: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('rooms.store'));
    };

    const handleJoin: FormEventHandler = (e) => {
        e.preventDefault();
        joinForm.post(route('rooms.join'));
    };

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
                                <Button type="submit" className="w-full bg-purple-600 hover:bg-purple-700" disabled={createForm.processing}>
                                    Create Room
                                </Button>
                                {createForm.errors.total_rounds && (
                                    <p className="text-sm text-red-500">{createForm.errors.total_rounds}</p>
                                )}
                            </form>
                        ) : (
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
                        )}
                    </CardContent>
                </Card>

                <p className="mt-6 text-sm text-white/60">
                    <a href={route('login')} className="underline">Sign in</a> or <a href={route('register')} className="underline">register</a> to play
                </p>
            </div>
        </>
    );
}
