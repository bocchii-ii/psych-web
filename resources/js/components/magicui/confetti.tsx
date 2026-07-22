import type { GlobalOptions as ConfettiGlobalOptions, CreateTypes as ConfettiInstance, Options as ConfettiOptions } from 'canvas-confetti';
import confetti from 'canvas-confetti';
import { ComponentPropsWithRef, createContext, forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef } from 'react';

type Api = {
    fire: (options?: ConfettiOptions) => void;
};

type ConfettiProps = ComponentPropsWithRef<'canvas'> & {
    options?: ConfettiOptions;
    globalOptions?: ConfettiGlobalOptions;
    manualstart?: boolean;
};

export type ConfettiRef = Api | null;

const ConfettiContext = createContext<Api>({} as Api);

/**
 * MagicUI Confetti — https://magicui.design/docs/components/confetti
 * A canvas-confetti wrapper exposing an imperative `fire()` API via ref,
 * used to build presets like a "fireworks" burst sequence.
 */
export const Confetti = forwardRef<ConfettiRef, ConfettiProps>((props, ref) => {
    const { options, globalOptions = { resize: true, useWorker: true }, manualstart = false, children, ...rest } = props;
    const instanceRef = useRef<ConfettiInstance | null>(null);

    const canvasRef = useCallback(
        (node: HTMLCanvasElement | null) => {
            if (node !== null) {
                if (instanceRef.current) return;
                instanceRef.current = confetti.create(node, { ...globalOptions, resize: true });
            } else if (instanceRef.current) {
                instanceRef.current.reset();
                instanceRef.current = null;
            }
        },
        [globalOptions],
    );

    const fire = useCallback(
        async (opts: ConfettiOptions = {}) => {
            try {
                await instanceRef.current?.({ ...options, ...opts });
            } catch (error) {
                console.error('Confetti error:', error);
            }
        },
        [options],
    );

    const api = useMemo(() => ({ fire }), [fire]);

    useImperativeHandle(ref, () => api, [api]);

    useEffect(() => {
        if (!manualstart) {
            fire();
        }
    }, [manualstart, fire]);

    return (
        <ConfettiContext.Provider value={api}>
            <canvas ref={canvasRef} {...rest} />
            {children}
        </ConfettiContext.Provider>
    );
});

Confetti.displayName = 'Confetti';
