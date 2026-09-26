import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type HeadingLevel = 1 | 2 | 3 | 4 | 5 | 6;
type HeadingVariant = 'display' | 'page' | 'section' | 'subsection' | 'group';
type HeadingTone = 'default' | 'muted' | 'primary' | 'inverted';
type HeadingAlign = 'start' | 'center' | 'end';

export type HeadingProps = {
    children: ReactNode;
    level?: HeadingLevel;
    variant?: HeadingVariant;
    tone?: HeadingTone;
    align?: HeadingAlign;
    id?: string;
    className?: never;
    style?: never;
};

const variantMap: Record<HeadingVariant, string> = {
    display:
        'font-serif text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight',
    page: 'font-serif text-3xl sm:text-4xl font-bold tracking-tight',
    section: 'font-serif text-2xl sm:text-3xl font-semibold tracking-tight',
    subsection: 'font-sans text-xl sm:text-2xl font-semibold tracking-tight',
    group: 'font-sans text-lg font-semibold tracking-tight',
};

const toneMap: Record<HeadingTone, string> = {
    default: 'text-foreground',
    muted: 'text-muted-foreground',
    primary: 'text-primary',
    inverted: 'text-surface-inverted-foreground',
};

const alignMap: Record<HeadingAlign, string> = {
    start: 'text-left',
    center: 'text-center',
    end: 'text-right',
};

export function Heading({
    children,
    level = 2,
    variant = 'section',
    tone = 'default',
    align = 'start',
    id,
}: HeadingProps) {
    const Tag = `h${level}` as const;

    return (
        <Tag
            id={id}
            className={cn(variantMap[variant], toneMap[tone], alignMap[align])}
        >
            {children}
        </Tag>
    );
}
