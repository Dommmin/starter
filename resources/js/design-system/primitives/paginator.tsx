import { ChevronLeft, ChevronRight } from 'lucide-react';
import { IconButton } from './icon-button';

export type PaginatorProps = {
    page: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    previousLabel: string;
    nextLabel: string;
    /** Translated summary string, passed from caller via useTranslation. */
    summary: string;
};

/** Previous/next pager; both controls are 44×44 px IconButtons. */
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
                <IconButton
                    icon={ChevronLeft}
                    ariaLabel={previousLabel}
                    variant="outline"
                    size="sm"
                    disabled={!canGoPrevious}
                    onClick={() => onPageChange(page - 1)}
                />
                <IconButton
                    icon={ChevronRight}
                    ariaLabel={nextLabel}
                    variant="outline"
                    size="sm"
                    disabled={!canGoNext}
                    onClick={() => onPageChange(page + 1)}
                />
            </div>
        </nav>
    );
}
