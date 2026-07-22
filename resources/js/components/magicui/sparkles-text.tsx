import { cn } from '@/lib/utils';
import { AnimatePresence, motion } from 'motion/react';
import { CSSProperties, ReactElement, useEffect, useState } from 'react';

interface Sparkle {
    id: string;
    x: string;
    y: string;
    color: string;
    delay: number;
    scale: number;
    lifespan: number;
}

const DEFAULT_COLORS = { first: '#FFD700', second: '#FE8BBB' };

const random = (min: number, max: number) => Math.random() * (max - min) + min;

let sparkleIdCounter = 0;
const nextSparkleId = () => `sparkle-${Date.now()}-${sparkleIdCounter++}`;

const generateSparkle = (colors: { first: string; second: string }): Sparkle => ({
    id: nextSparkleId(),
    x: `${random(0, 100)}%`,
    y: `${random(0, 100)}%`,
    color: Math.random() > 0.5 ? colors.first : colors.second,
    delay: random(0, 2),
    scale: random(0.3, 1),
    lifespan: random(5, 15),
});

interface SparklesTextProps {
    className?: string;
    text: string;
    sparklesCount?: number;
    colors?: { first: string; second: string };
}

/**
 * MagicUI SparklesText — https://magicui.design/docs/components/sparkles-text
 * Renders text with a continuously animated field of sparkles behind it.
 */
export function SparklesText({ text, colors = DEFAULT_COLORS, className, sparklesCount = 12 }: SparklesTextProps) {
    const [sparkles, setSparkles] = useState<Sparkle[]>([]);

    useEffect(() => {
        setSparkles(Array.from({ length: sparklesCount }, () => generateSparkle(colors)));

        const interval = setInterval(() => {
            setSparkles((current) =>
                current.map((sparkle) => (sparkle.lifespan <= 0 ? generateSparkle(colors) : { ...sparkle, lifespan: sparkle.lifespan - 0.1 })),
            );
        }, 100);

        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sparklesCount]);

    return (
        <span className={cn('relative inline-block', className)}>
            <AnimatePresence>
                {sparkles.map((sparkle) => (
                    <SparkleIcon key={sparkle.id} {...sparkle} />
                ))}
            </AnimatePresence>
            <strong className="relative z-10">{text}</strong>
        </span>
    );
}

function SparkleIcon({ x, y, color, delay, scale }: Sparkle): ReactElement {
    const style: CSSProperties = { position: 'absolute', left: x, top: y, transform: 'translate(-50%, -50%)' };

    return (
        <motion.svg
            style={style}
            className="pointer-events-none z-20"
            initial={{ opacity: 0, scale: 0, rotate: 75 }}
            animate={{ opacity: [0, 1, 0], scale: [0, scale, 0], rotate: 120 }}
            transition={{ duration: 0.9, repeat: Infinity, delay, ease: 'easeInOut' }}
            width="21"
            height="21"
            viewBox="0 0 21 21"
        >
            <path
                d="M9.82531 0.843845C10.0553 0.215178 10.9446 0.215178 11.1746 0.843845L11.8618 2.72026C12.4006 4.19229 12.3916 6.39157 13.5 7.5C14.6084 8.60843 16.8077 8.59935 18.2797 9.13822L20.1561 9.82534C20.7858 10.0553 20.7858 10.9447 20.1561 11.1747L18.2797 11.8618C16.8077 12.4007 14.6084 12.3916 13.5 13.5C12.3916 14.6084 12.4006 16.8077 11.8618 18.2798L11.1746 20.1562C10.9446 20.7858 10.0553 20.7858 9.82531 20.1562L9.13819 18.2798C8.59932 16.8077 8.60843 14.6084 7.5 13.5C6.39157 12.3916 4.19225 12.4007 2.72023 11.8618L0.843814 11.1747C0.215148 10.9447 0.215148 10.0553 0.843814 9.82534L2.72023 9.13822C4.19225 8.59935 6.39157 8.60843 7.5 7.5C8.60843 6.39157 8.59932 4.19229 9.13819 2.72026L9.82531 0.843845Z"
                fill={color}
            />
        </motion.svg>
    );
}
