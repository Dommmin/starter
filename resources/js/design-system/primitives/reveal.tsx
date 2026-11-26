import { useEffect, useRef, useState, type ReactNode } from 'react';
import { prefersReducedMotion } from '@/lib/reduced-motion';
import { cn } from '@/lib/utils';

type RevealState = 'static' | 'hidden' | 'shown';

export type RevealProps = {
    children: ReactNode;
    /** Anchor id of the revealed block (e.g. a home section `#portfolio`). */
    id?: string;
    className?: never;
    style?: never;
};

/** Time after which the finished reveal drops its transition classes. */
const SETTLE_MS = 1200;

const transition = cn(
    'transition-[opacity,translate] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
    '[&_.grid>*]:transition-[opacity,translate] [&_.grid>*]:duration-700 [&_.grid>*]:ease-[cubic-bezier(0.22,1,0.36,1)]',
    '[&_.grid>*:nth-child(2)]:delay-[80ms] [&_.grid>*:nth-child(3)]:delay-[160ms] [&_.grid>*:nth-child(4)]:delay-[240ms]',
    '[&_.grid>*:nth-child(5)]:delay-[320ms] [&_.grid>*:nth-child(n+6)]:delay-[400ms]',
);

const hidden =
    'translate-y-6 opacity-0 [&_.grid>*]:translate-y-8 [&_.grid>*]:opacity-0';

/**
 * Fades and slides a block in once it scrolls into view; items of grids
 * inside it (cards, features) follow one after another. Content is always
 * visible in the server HTML and without JavaScript: only a block that is
 * below the fold after hydration is hidden until it is reached, so content
 * on the first screen never animates (no LCP or CLS cost). With
 * `prefers-reduced-motion: reduce` or the reduced-motion
 * accessibility preference nothing animates.
 */
export function Reveal({ children, id }: RevealProps) {
    const ref = useRef<HTMLDivElement>(null);
    const [state, setState] = useState<RevealState>('static');

    useEffect(() => {
        const element = ref.current;

        if (
            !element ||
            typeof IntersectionObserver === 'undefined' ||
            prefersReducedMotion() ||
            element.getBoundingClientRect().top < window.innerHeight
        ) {
            return;
        }

        let settle: ReturnType<typeof setTimeout> | undefined;
        setState('hidden');

        const observer = new IntersectionObserver(
            (entries) => {
                if (!entries.some((entry) => entry.isIntersecting)) {
                    return;
                }

                observer.disconnect();
                setState('shown');
                settle = setTimeout(() => setState('static'), SETTLE_MS);
            },
            { rootMargin: '0px 0px -10% 0px' },
        );
        observer.observe(element);

        return () => {
            observer.disconnect();
            clearTimeout(settle);
        };
    }, []);

    return (
        <div
            ref={ref}
            id={id}
            data-reveal={state}
            className={cn(
                state !== 'static' && transition,
                state === 'hidden' && hidden,
            )}
        >
            {children}
        </div>
    );
}
