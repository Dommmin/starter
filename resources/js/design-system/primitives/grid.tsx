import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type GridLayout = 'single' | 'split' | 'cards' | 'features';
type GridGap = 'tight' | 'default' | 'relaxed';
type GridAlign = 'start' | 'center';

export type GridProps = {
    children: ReactNode;
    layout?: GridLayout;
    gap?: GridGap;
    /**
     * `center` (cards layout only): cards keep their width and an incomplete
     * row is centred, for centred sections with one or two cards.
     */
    align?: GridAlign;
    className?: never;
    style?: never;
};

const layoutMap: Record<GridLayout, string> = {
    single: 'grid-cols-1',
    split: 'grid-cols-1 md:grid-cols-2',
    cards: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    features: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
};

const gapMap: Record<GridGap, string> = {
    tight: 'gap-3 sm:gap-4',
    default: 'gap-6 sm:gap-8',
    relaxed: 'gap-8 sm:gap-12',
};

export function Grid({
    children,
    layout = 'cards',
    gap = 'default',
    align = 'start',
}: GridProps) {
    const columns =
        align === 'center' && layout === 'cards'
            ? 'grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),20rem))] justify-center'
            : layoutMap[layout];

    return (
        <div className={cn('grid w-full', columns, gapMap[gap])}>
            {children}
        </div>
    );
}
