import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export type PaginatorProps = {
    page: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    previousLabel: string;
    nextLabel: string;
    /** Translated summary string, passed from caller via useTranslation. */
    summary: string;
};

export function Paginator({
    page,
    totalPages,
    onPageChange,
    previousLabel,
    nextLabel,
    summary,
}: PaginatorProps) {
    const canGoPrevious = page > 1;
    const canGoNext = page < totalPages;

    return (
        <nav
            aria-label={summary}
            className="flex items-center justify-between gap-4 py-2"
        >
            <p className="text-muted-foreground text-sm">{summary}</p>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => onPageChange(page - 1)}
                    disabled={!canGoPrevious}
                    aria-label={previousLabel}
                    className={cn(
                        'border-border-subtle focus-visible:ring-ring inline-flex size-9 items-center justify-center rounded-md border',
                        'hover:bg-surface-subtle focus-visible:ring-2 focus-visible:outline-none',
                        'disabled:pointer-events-none disabled:opacity-50',
                    )}
                >
                    <ChevronLeft className="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    onClick={() => onPageChange(page + 1)}
                    disabled={!canGoNext}
                    aria-label={nextLabel}
                    className={cn(
                        'border-border-subtle focus-visible:ring-ring inline-flex size-9 items-center justify-center rounded-md border',
                        'hover:bg-surface-subtle focus-visible:ring-2 focus-visible:outline-none',
                        'disabled:pointer-events-none disabled:opacity-50',
                    )}
                >
                    <ChevronRight className="size-4" aria-hidden="true" />
                </button>
            </div>
        </nav>
    );
}
