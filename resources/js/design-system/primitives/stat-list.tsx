import { useEffect, useRef, useState, type RefObject } from 'react';
import { cn } from '@/lib/utils';

export type StatListItem = {
    id: string;
    /** Display text of the figure, e.g. `300+` or `98%`. */
    value: string;
    /** What the figure counts, e.g. "projects delivered". */
    label: string;
};

type StatListAlign = 'start' | 'center';

export type StatListProps = {
    items: StatListItem[];
    align?: StatListAlign;
    /**
     * Count the leading number of each value up from zero once the list
     * scrolls into view. The server HTML and reduced motion keep the final
     * values; text around the number (`+`, `%`) never moves.
     */
    animate?: boolean;
    className?: never;
    style?: never;
};

const COUNT_MS = 1400;

/** Leading integer of a figure and the text around it, e.g. `120+`. */
function splitFigure(
    value: string,
): { prefix: string; number: number; suffix: string } | null {
    const match = /^(\D*)(\d+)(.*)$/.exec(value);

    return match
        ? { prefix: match[1], number: Number(match[2]), suffix: match[3] }
        : null;
}

/** Progress 0–1 of the count, `null` while it shows the final values. */
function useCountProgress(
    enabled: boolean,
): [RefObject<HTMLDListElement | null>, number | null] {
    const ref = useRef<HTMLDListElement>(null);
    const [progress, setProgress] = useState<number | null>(null);

    useEffect(() => {
        const element = ref.current;

        if (
            !enabled ||
            !element ||
            typeof IntersectionObserver === 'undefined' ||
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        let frame = 0;
        setProgress(0);

        const observer = new IntersectionObserver(
            (entries) => {
                if (!entries.some((entry) => entry.isIntersecting)) {
                    return;
                }

                observer.disconnect();
                const start = performance.now();
                const tick = (now: number) => {
                    const linear = Math.min((now - start) / COUNT_MS, 1);

                    if (linear === 1) {
                        setProgress(null);

                        return;
                    }

                    // Ease out: fast start, gentle landing on the value.
                    setProgress(1 - Math.pow(1 - linear, 4));
                    frame = requestAnimationFrame(tick);
                };
                frame = requestAnimationFrame(tick);
            },
            { rootMargin: '0px 0px -10% 0px' },
        );
        observer.observe(element);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [enabled]);

    return [ref, progress];
}

function displayValue(value: string, progress: number | null): string {
    const figure = progress === null ? null : splitFigure(value);

    return figure && progress !== null
        ? `${figure.prefix}${Math.round(figure.number * progress)}${figure.suffix}`
        : value;
}

/**
 * Key figures as a description list: a large value above its label, two
 * columns on phones (three when there are exactly three) and up to four on
 * wide screens. Values are plain text, so they are never headings and keep
 * the page outline intact; tabular digits keep counting values steady.
 */
export function StatList({
    items,
    align = 'center',
    animate = false,
}: StatListProps) {
    const [ref, progress] = useCountProgress(animate);

    if (items.length === 0) {
        return null;
    }

    return (
        <dl
            ref={ref}
            className={cn(
                'grid gap-x-6 gap-y-8',
                items.length === 3 ? 'grid-cols-3 gap-x-3' : 'grid-cols-2',
                items.length >= 4 && 'lg:grid-cols-4',
            )}
        >
            {items.map((item) => (
                <div
                    key={item.id}
                    className={cn(
                        'flex min-w-0 flex-col-reverse justify-end gap-1',
                        align === 'center' && 'items-center text-center',
                    )}
                >
                    <dt className="text-muted-foreground text-xs font-medium wrap-anywhere sm:text-sm">
                        {item.label}
                    </dt>
                    <dd className="text-primary font-heading text-3xl font-bold tracking-tight tabular-nums sm:text-5xl">
                        {/* The final value stays readable to assistive technology while it counts. */}
                        <span aria-hidden={progress !== null || undefined}>
                            {displayValue(item.value, progress)}
                        </span>
                        {progress !== null && (
                            <span className="sr-only">{item.value}</span>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
