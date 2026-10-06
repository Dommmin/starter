import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type TextVariant = 'lead' | 'body' | 'label' | 'caption' | 'code';
type TextTone =
    | 'default'
    | 'muted'
    | 'subtle'
    | 'primary'
    | 'inverted'
    | 'success'
    | 'danger';
type TextAlign = 'start' | 'center' | 'end';

export type TextProps = {
    children: ReactNode;
    variant?: TextVariant;
    tone?: TextTone;
    align?: TextAlign;
    as?: 'p' | 'span' | 'div' | 'label' | 'blockquote';
    className?: never;
    style?: never;
};

const variantMap: Record<TextVariant, string> = {
    lead: 'text-lg sm:text-xl font-normal leading-relaxed',
    body: 'text-sm sm:text-base font-normal leading-normal',
    label: 'text-xs sm:text-sm font-medium tracking-normal',
    caption: 'text-xs font-normal text-muted-foreground',
    code: 'font-mono text-xs sm:text-sm',
};

const toneMap: Record<TextTone, string> = {
    default: 'text-foreground',
    muted: 'text-muted-foreground',
    subtle: 'text-text-subtle',
    primary: 'text-primary',
    inverted: 'text-surface-emphasis-foreground',
    success: 'text-status-success',
    danger: 'text-destructive',
};

const alignMap: Record<TextAlign, string> = {
    start: 'text-left',
    center: 'text-center',
    end: 'text-right',
};

export function Text({
    children,
    variant = 'body',
    tone = 'default',
    align = 'start',
    as: Component = 'p',
}: TextProps) {
    return (
        <Component
            className={cn(variantMap[variant], toneMap[tone], alignMap[align])}
        >
            {children}
        </Component>
    );
}
