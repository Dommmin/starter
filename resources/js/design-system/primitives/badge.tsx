import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type BadgeTone = 'neutral' | 'primary' | 'success' | 'danger' | 'outline';

export type BadgeProps = {
    children: ReactNode;
    tone?: BadgeTone;
};

/**
 * Text/background pairs measured at >= 4.5:1 for 12 px text in both themes
 * (2026-09-30, lowest: danger 5.3:1 light). Tone identity comes from the
 * tinted background and border, not from low-contrast tinted text.
 */
const toneMap: Record<BadgeTone, string> = {
    neutral: 'bg-secondary text-secondary-foreground border-transparent',
    primary: 'bg-primary/10 text-foreground border-primary/40',
    success:
        'bg-status-success-subtle text-foreground border-status-success/40',
    danger: 'bg-status-danger-subtle text-status-danger border-status-danger/30',
    outline: 'border-border-subtle text-foreground bg-transparent',
};

export function Badge({ children, tone = 'neutral' }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                // Child icons (raw Lucide SVGs default to 24px) follow the text size.
                "[&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-3",
                toneMap[tone],
            )}
        >
            {children}
        </span>
    );
}
