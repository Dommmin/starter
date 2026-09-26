import { AlertTriangle, CheckCircle2, Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type AlertTone = 'neutral' | 'success' | 'danger';

export type AlertProps = {
    title: ReactNode;
    description?: ReactNode;
    tone?: AlertTone;
    /** Translated label for the dismiss control; omit to render a non-dismissible alert. */
    dismissLabel?: string;
    onDismiss?: () => void;
};

const toneMap: Record<AlertTone, { container: string; icon: string }> = {
    neutral: {
        container: 'bg-surface-subtle text-foreground border-border-subtle',
        icon: 'text-foreground',
    },
    success: {
        container:
            'bg-status-success/10 text-foreground border-status-success/30',
        icon: 'text-status-success',
    },
    danger: {
        container: 'bg-destructive/10 text-foreground border-destructive/30',
        icon: 'text-destructive',
    },
};

const iconMap: Record<AlertTone, typeof Info> = {
    neutral: Info,
    success: CheckCircle2,
    danger: AlertTriangle,
};

export function Alert({
    title,
    description,
    tone = 'neutral',
    dismissLabel,
    onDismiss,
}: AlertProps) {
    const IconComponent = iconMap[tone];
    const isDestructive = tone === 'danger';

    return (
        <div
            role={isDestructive ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
                toneMap[tone].container,
            )}
        >
            <IconComponent
                className={cn('mt-0.5 size-4 shrink-0', toneMap[tone].icon)}
                aria-hidden="true"
            />
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="font-medium">{title}</p>
                {description && (
                    <p className="text-muted-foreground">{description}</p>
                )}
            </div>
            {onDismiss && dismissLabel && (
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label={dismissLabel}
                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring -m-1.5 shrink-0 rounded-full p-1.5 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            )}
        </div>
    );
}
