import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

type SpinnerSize = 'sm' | 'default' | 'lg';

export type SpinnerProps = {
    /** Translated accessible label, e.g. t('actions.loading'). */
    label: string;
    size?: SpinnerSize;
};

const sizeMap: Record<SpinnerSize, string> = {
    sm: 'size-4',
    default: 'size-5',
    lg: 'size-6',
};

export function Spinner({ label, size = 'default' }: SpinnerProps) {
    return (
        <span role="status" className="inline-flex items-center">
            <Loader2
                className={cn(
                    'text-muted-foreground animate-spin',
                    sizeMap[size],
                )}
                aria-hidden="true"
            />
            <span className="sr-only">{label}</span>
        </span>
    );
}
