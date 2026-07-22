import { useEffect, useRef } from 'react';
import { Confetti, ConfettiRef } from './confetti';

const randomInRange = (min: number, max: number) => Math.random() * (max - min) + min;

/**
 * MagicUI Confetti — "Fireworks" preset.
 * Repeatedly fires single particles from random points along the bottom of
 * the screen for a few seconds, mimicking canvas-confetti's classic
 * fireworks demo. See https://magicui.design/docs/components/confetti
 */
export function ConfettiFireworks({ className }: { className?: string }) {
    const confettiRef = useRef<ConfettiRef>(null);

    useEffect(() => {
        const duration = 5 * 1000;
        const animationEnd = Date.now() + duration;
        let skew = 1;
        let frameId: number;

        const frame = () => {
            const timeLeft = animationEnd - Date.now();
            const ticks = Math.max(200, 500 * (timeLeft / duration));
            skew = Math.max(0.8, skew - 0.001);

            confettiRef.current?.fire({
                particleCount: 1,
                startVelocity: 0,
                ticks,
                origin: {
                    x: Math.random(),
                    y: Math.random() * skew - 0.2,
                },
                colors: ['#FFC700', '#FF0000', '#2E3191', '#41BBC7'],
                shapes: ['circle'],
                gravity: randomInRange(0.4, 0.6),
                scalar: randomInRange(0.4, 1),
                drift: randomInRange(-0.4, 0.4),
            });

            if (timeLeft > 0) {
                frameId = requestAnimationFrame(frame);
            }
        };

        frame();

        return () => cancelAnimationFrame(frameId);
    }, []);

    return <Confetti ref={confettiRef} manualstart className={className} />;
}
