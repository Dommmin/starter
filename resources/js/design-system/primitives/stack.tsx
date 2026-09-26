import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type StackGap = 'none' | 'tight' | 'default' | 'relaxed';
type StackAlign = 'start' | 'center' | 'end' | 'stretch';
type StackJustify = 'start' | 'center' | 'end' | 'between';

export type StackProps = {
    children: ReactNode;
    gap?: StackGap;
    align?: StackAlign;
    justify?: StackJustify;
    as?: 'div' | 'section' | 'nav' | 'ul' | 'ol';
    className?: never;
    style?: never;
};

const gapMap: Record<StackGap, string> = {
    none: 'gap-0',
    tight: 'gap-2 sm:gap-3',
    default: 'gap-4 sm:gap-6',
    relaxed: 'gap-8 sm:gap-12',
};

const alignMap: Record<StackAlign, string> = {
    start: 'items-start',
    center: 'items-center',
    end: 'items-end',
    stretch: 'items-stretch',
};

const justifyMap: Record<StackJustify, string> = {
    start: 'justify-start',
    center: 'justify-center',
    end: 'justify-end',
    between: 'justify-between',
};

export function Stack({
    children,
    gap = 'default',
    align = 'stretch',
    justify = 'start',
    as: Component = 'div',
}: StackProps) {
    return (
        <Component
            className={cn(
                'flex flex-col',
                gapMap[gap],
                alignMap[align],
                justifyMap[justify],
            )}
        >
            {children}
        </Component>
    );
}
