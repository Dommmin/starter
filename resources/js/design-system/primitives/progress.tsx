import { cn } from '@/lib/utils';

type ProgressTone = 'default' | 'success' | 'danger';

export type ProgressProps = {
    /** Translated accessible name for the progress operation. */
    label: string;
    /** 0-100. Omit to render an indeterminate (busy, unknown duration) bar. */
    value?: number;
    tone?: ProgressTone;
};

const toneMap: Record<ProgressTone, string> = {
    default: 'bg-primary',
    success: 'bg-status-success',
    danger: 'bg-destructive',
};

export function Progress({ label, value, tone = 'default' }: ProgressProps) {
    const clamped =
        value === undefined ? undefined : Math.min(100, Math.max(0, value));

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={clamped}
            className="bg-surface-subtle h-2 w-full overflow-hidden rounded-full"
        >
            <div
                className={cn(
                    'h-full rounded-full transition-[width] duration-300',
                    toneMap[tone],
                    clamped === undefined && 'w-1/3 animate-pulse',
                )}
                style={
                    clamped === undefined ? undefined : { width: `${clamped}%` }
                }
            />
        </div>
    );
}
