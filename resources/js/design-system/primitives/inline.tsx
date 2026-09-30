import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type InlineGap = 'none' | 'tight' | 'default' | 'relaxed';
type InlineAlign = 'start' | 'center' | 'end' | 'baseline' | 'stretch';
type InlineJustify = 'start' | 'center' | 'end' | 'between';

export type InlineProps = {
    children: ReactNode;
    gap?: InlineGap;
    /** Cross-axis alignment; `stretch` gives a vertical `Separator` full row height. */
    align?: InlineAlign;
    justify?: InlineJustify;
    /** Lets items flow onto the next line instead of overflowing on narrow screens. */
    wrap?: boolean;
    className?: never;
    style?: never;
};

const gapMap: Record<InlineGap, string> = {
    none: 'gap-0',
    tight: 'gap-2',
    default: 'gap-3',
    relaxed: 'gap-6',
};

const alignMap: Record<InlineAlign, string> = {
    start: 'items-start',
    center: 'items-center',
    end: 'items-end',
    baseline: 'items-baseline',
    stretch: 'items-stretch',
};

const justifyMap: Record<InlineJustify, string> = {
    start: 'justify-start',
    center: 'justify-center',
    end: 'justify-end',
    between: 'justify-between',
};

/**
 * Horizontal row of items (actions, badges, meta) — the row counterpart of
 * `Stack`, with the same closed props and no styling escape hatch.
 */
export function Inline({
    children,
    gap = 'tight',
    align = 'center',
    justify = 'start',
    wrap = false,
}: InlineProps) {
    return (
        <div
            className={cn(
                'flex flex-row',
                gapMap[gap],
                alignMap[align],
                justifyMap[justify],
                wrap && 'flex-wrap',
            )}
        >
            {children}
        </div>
    );
}
