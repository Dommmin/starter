import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type BadgeTone = 'neutral' | 'primary' | 'success' | 'danger' | 'outline';

export type BadgeProps = {
    children: ReactNode;
    tone?: BadgeTone;
};

const toneMap: Record<BadgeTone, string> = {
    neutral: 'bg-secondary text-secondary-foreground',
    primary: 'bg-primary/10 text-primary',
    success: 'bg-status-success text-status-success-foreground',
    danger: 'bg-destructive text-destructive-foreground',
    outline: 'border-border-subtle text-foreground border bg-transparent',
};

export function Badge({ children, tone = 'neutral' }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                // Child icons (raw Lucide SVGs default to 24px) follow the text size.
                "[&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-3",
                toneMap[tone],
            )}
        >
            {children}
        </span>
    );
}
