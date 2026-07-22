import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle, Clock, Crown, XCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────

type Phase = 'waiting' | 'question' | 'voting' | 'reveal' | 'finished';

interface Player {
    id: number;
    name: string;
    score: number;
}

interface Room {
    id: number;
    code: string;
    status: string;
    total_rounds: number;
    current_round: number;
    host_id: number;
}

interface Auth {
    user: { id: number; name: string };
}

interface AnswerOption {
    id: number | null;
    text: string;
    is_correct?: boolean;
    author?: string | null;
    voters?: string[];
    points_earned?: number;
}

interface LeaderboardEntry {
    user_id: number;
    name: string;
    score: number;
    delta?: number;
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function Game({
    room: initialRoom,
    players: initialPlayers,
    auth,
    question: initialQuestion,
    time_left: initialTimeLeft,
    is_spectator: isSpectator,
}: {
    room: Room;
    players: Player[];
    auth: Auth;
    question?: string | null;
    time_left?: number;
    is_spectator?: boolean;
}) {
    const [phase, setPhase] = useState<Phase>(initialRoom.status as Phase);
    const [round, setRound] = useState(initialRoom.current_round);
    const [totalRounds, setTotalRounds] = useState(initialRoom.total_rounds);
    const [question, setQuestion] = useState(initialQuestion ?? '');
    const [answer, setAnswer] = useState('');
    const [submitted, setSubmitted] = useState(false);
    const [submitError, setSubmitError] = useState('');
    const [submittedPlayers, setSubmittedPlayers] = useState<string[]>([]);
    const [votedPlayers, setVotedPlayers] = useState<string[]>([]);
    const [answers, setAnswers] = useState<AnswerOption[]>([]);
    const [selectedAnswerId, setSelectedAnswerId] = useState<number | null | undefined>(undefined);
    const [voted, setVoted] = useState(false);
    const [voteError, setVoteError] = useState('');
    const [reveal, setReveal] = useState<{ correctAnswer: string; answers: AnswerOption[]; leaderboard: LeaderboardEntry[] } | null>(null);
    const [players, setPlayers] = useState<Player[]>(initialPlayers);
    const [timeLeft, setTimeLeft] = useState(0);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const isHost = auth.user.id === initialRoom.host_id;

    // ── Timer ──────────────────────────────────────────────────────────────────

    const startTimer = (seconds: number) => {
        if (timerRef.current) clearInterval(timerRef.current);
        setTimeLeft(seconds);
        timerRef.current = setInterval(() => {
            setTimeLeft((t) => {
                if (t <= 1) {
                    clearInterval(timerRef.current!);
                    return 0;
                }
                return t - 1;
            });
        }, 1000);
    };

    // ── Echo subscriptions ─────────────────────────────────────────────────────

    useEffect(() => {
        const channel = window.Echo.private(`room.${initialRoom.code}`);

        channel
            .listen('.RoundStarted', (e: { roundNumber: number; totalRounds: number; questionBody: string; timeLimit: number }) => {
                setRound(e.roundNumber);
                setTotalRounds(e.totalRounds);
                setQuestion(e.questionBody);
                setPhase('question');
                setAnswer('');
                setSubmitted(false);
                setSubmitError('');
                setSubmittedPlayers([]);
                setVotedPlayers([]);
                setAnswers([]);
                setSelectedAnswerId(undefined);
                setVoted(false);
                setVoteError('');
                setReveal(null);
                startTimer(e.timeLimit);
            })
            .listen('.PlayerSubmitted', (e: { playerName: string; submittedCount: number; totalCount: number }) => {
                setSubmittedPlayers((prev) => [...new Set([...prev, e.playerName])]);
            })
            .listen('.VotingStarted', (e: { answers: AnswerOption[]; timeLimit: number }) => {
                setAnswers(e.answers);
                setPhase('voting');
                startTimer(e.timeLimit);
            })
            .listen('.PlayerVoted', (e: { playerName: string }) => {
                setVotedPlayers((prev) => [...new Set([...prev, e.playerName])]);
            })
            .listen('.RoundRevealed', (e: { correctAnswer: string; answers: AnswerOption[]; leaderboard: LeaderboardEntry[]; timeLimit: number }) => {
                setReveal(e);
                setPhase('reveal');
                startTimer(e.timeLimit);
                setPlayers((prev) =>
                    prev.map((p) => {
                        const entry = e.leaderboard.find((l) => l.user_id === p.id);
                        return entry ? { ...p, score: entry.score } : p;
                    }),
                );
            })
            .listen('.GameEnded', (e: { leaderboard: LeaderboardEntry[] }) => {
                setPhase('finished');
                router.visit(route('rooms.end', initialRoom.code));
            });

        return () => {
            channel.stopListening('.RoundStarted');
            channel.stopListening('.PlayerSubmitted');
            channel.stopListening('.VotingStarted');
            channel.stopListening('.PlayerVoted');
            channel.stopListening('.RoundRevealed');
            channel.stopListening('.GameEnded');
        };
    }, [initialRoom.code]);

    useEffect(() => () => { if (timerRef.current) clearInterval(timerRef.current); }, []);

    // Resume the countdown if the page was (re)loaded mid-question/reveal phase,
    // e.g. when a player's broadcast arrived before they subscribed.
    useEffect(() => {
        if ((initialRoom.status === 'question' || initialRoom.status === 'reveal') && initialTimeLeft) {
            startTimer(initialTimeLeft);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ── Actions ────────────────────────────────────────────────────────────────

    const submitAnswer: FormEventHandler = async (e) => {
        e.preventDefault();
        setSubmitError('');
        try {
            await axios.post(route('game.submit', initialRoom.code), { answer });
            setSubmitted(true);
        } catch (err: any) {
            setSubmitError(err.response?.data?.message ?? 'Could not submit answer.');
        }
    };

    const submitVote = async (id: number | null) => {
        if (voted || selectedAnswerId !== undefined) return;
        setSelectedAnswerId(id);
        setVoteError('');
        try {
            await axios.post(route('game.vote', initialRoom.code), { submission_id: id });
            setVoted(true);
        } catch (err: any) {
            setVoteError(err.response?.data?.message ?? 'Could not submit vote.');
            setSelectedAnswerId(undefined);
        }
    };

    const nextRound = () => {
        axios.post(route('game.next', initialRoom.code));
    };

    // ── Render ─────────────────────────────────────────────────────────────────

    const timerColor = timeLeft > 15 ? 'text-green-500' : timeLeft > 5 ? 'text-yellow-500' : 'text-red-500';

    return (
        <>
            <Head title={`Round ${round} / ${totalRounds}`} />
            <div className="flex min-h-screen bg-gradient-to-br from-purple-600 to-indigo-800 p-4">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">

                    {/* Header bar */}
                    <div className="flex items-center justify-between text-white">
                        <span className="font-bold">Round {round} / {totalRounds}</span>
                        {(phase === 'question' || phase === 'voting' || phase === 'reveal') && (
                            <span className={`flex items-center gap-1 font-mono text-xl font-bold ${timerColor}`}>
                                <Clock className="h-5 w-5" /> {timeLeft}s
                            </span>
                        )}
                        <span className="text-sm opacity-70">
                            {players.length} players{isSpectator && ' · Spectating'}
                        </span>
                    </div>

                    {/* ── QUESTION PHASE ── */}
                    {phase === 'question' && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-center text-lg leading-snug">{question}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {isSpectator ? (
                                    <p className="py-4 text-center text-sm text-muted-foreground">
                                        You're spectating — watching players write their fake answers…
                                    </p>
                                ) : !submitted ? (
                                    <form onSubmit={submitAnswer} className="space-y-3">
                                        <p className="text-center text-sm text-muted-foreground">
                                            Write a convincing fake answer!
                                        </p>
                                        <Input
                                            value={answer}
                                            onChange={(e) => setAnswer(e.target.value)}
                                            placeholder="Your fake answer…"
                                            maxLength={200}
                                            autoFocus
                                        />
                                        {submitError && <p className="text-sm text-red-500">{submitError}</p>}
                                        <Button type="submit" className="w-full bg-purple-600 hover:bg-purple-700" disabled={!answer.trim()}>
                                            Submit Answer
                                        </Button>
                                    </form>
                                ) : (
                                    <p className="py-4 text-center text-green-600 font-semibold">
                                        Answer submitted! Waiting for others…
                                    </p>
                                )}

                                {/* Submission progress */}
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {players.map((p) => (
                                        <div
                                            key={p.id}
                                            className={`flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold ${
                                                submittedPlayers.includes(p.name)
                                                    ? 'bg-green-500 text-white'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                            title={p.name}
                                        >
                                            {p.name[0].toUpperCase()}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* ── VOTING PHASE ── */}
                    {phase === 'voting' && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-center text-lg leading-snug">{question}</CardTitle>
                                <p className="text-center text-sm text-muted-foreground">
                                    {isSpectator ? "You're spectating — watching players vote…" : 'Which answer is the real one?'}
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {answers.map((a, i) => {
                                    const isSelected = selectedAnswerId === a.id;
                                    return (
                                        <button
                                            key={i}
                                            onClick={() => submitVote(a.id ?? null)}
                                            disabled={voted || isSpectator}
                                            className={`w-full rounded-xl border-2 px-4 py-3 text-left text-sm font-medium transition-all ${
                                                isSelected
                                                    ? 'border-purple-600 bg-purple-50 dark:bg-purple-950'
                                                    : 'border-border bg-card hover:border-purple-400'
                                            } ${voted && !isSelected ? 'opacity-50' : ''} ${isSpectator ? 'cursor-default' : ''}`}
                                        >
                                            {a.text}
                                        </button>
                                    );
                                })}
                                {voteError && <p className="text-sm text-red-500">{voteError}</p>}
                                {voted && (
                                    <p className="text-center text-sm text-muted-foreground">
                                        Vote locked in! Waiting for others ({votedPlayers.length}/{players.length})…
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* ── REVEAL PHASE ── */}
                    {phase === 'reveal' && reveal && (
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-center text-lg leading-snug">{question}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {reveal.answers.map((a, i) => (
                                        <div
                                            key={i}
                                            className={`rounded-xl border-2 px-4 py-3 ${
                                                a.is_correct
                                                    ? 'border-green-500 bg-green-50 dark:bg-green-950'
                                                    : 'border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">{a.text}</span>
                                                {a.is_correct ? (
                                                    <CheckCircle className="h-5 w-5 text-green-500" />
                                                ) : (
                                                    <XCircle className="h-5 w-5 text-muted-foreground" />
                                                )}
                                            </div>
                                            {a.author && (
                                                <p className="mt-1 text-xs text-muted-foreground">by {a.author}</p>
                                            )}
                                            {a.voters && a.voters.length > 0 && (
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    {a.voters.map((v) => (
                                                        <span key={v} className="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                            {v}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>

                            {/* Leaderboard */}
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">Standings</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2">
                                        {reveal.leaderboard.map((entry, i) => (
                                            <li key={entry.user_id} className="flex items-center gap-3">
                                                <span className="w-5 text-center text-sm text-muted-foreground">{i + 1}</span>
                                                <span className="flex-1 font-medium">{entry.name}</span>
                                                {(entry.delta ?? 0) > 0 && (
                                                    <span className="text-sm font-semibold text-green-500">+{entry.delta}</span>
                                                )}
                                                <span className="font-bold">{entry.score}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>

                            {isHost && (
                                <Button className="w-full bg-purple-600 hover:bg-purple-700" onClick={nextRound}>
                                    {round >= totalRounds ? 'See Final Results' : `Next Round (auto in ${timeLeft}s)`}
                                </Button>
                            )}
                            {!isHost && (
                                <p className="text-center text-sm text-white/70">
                                    Waiting for host to continue… (auto-advances in {timeLeft}s)
                                </p>
                            )}
                        </div>
                    )}

                    {/* Sidebar scoreboard (always visible) */}
                    {phase !== 'reveal' && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">Scores</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-1">
                                    {[...players].sort((a, b) => b.score - a.score).map((p, i) => (
                                        <li key={p.id} className="flex items-center gap-2 text-sm">
                                            <span className="w-4 text-muted-foreground">{i + 1}</span>
                                            <span className="flex-1">{p.name}</span>
                                            <span className="font-semibold">{p.score}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
