import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Skeleton } from './skeleton';

export type DataTableColumn<Row> = {
    key: string;
    /** Translated column header. */
    header: string;
    render: (row: Row) => ReactNode;
    sortable?: boolean;
    align?: 'start' | 'end';
};

export type DataTableSort = {
    key: string;
    direction: 'asc' | 'desc';
};

export type DataTableProps<Row> = {
    /** Translated accessible table name, passed from caller via useTranslation. */
    caption: string;
    columns: DataTableColumn<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string | number;
    sort?: DataTableSort | null;
    /**
     * Called with the clicked column key; the caller owns URL/query state and
     * decides the next direction (this component is presentation-only).
     */
    onSortChange?: (key: string) => void;
    isLoading?: boolean;
    /** Translated error message; when set, an error row replaces the body. */
    error?: string;
    retryLabel?: string;
    onRetry?: () => void;
    /** Rendered instead of rows when `rows` is empty and there is no error/loading. */
    emptyState: ReactNode;
};

export function DataTable<Row>({
    caption,
    columns,
    rows,
    rowKey,
    sort = null,
    onSortChange,
    isLoading = false,
    error,
    retryLabel,
    onRetry,
    emptyState,
}: DataTableProps<Row>) {
    const showEmpty = !isLoading && !error && rows.length === 0;

    return (
        <div className="border-border-subtle overflow-x-auto rounded-lg border">
            <table className="w-full text-left text-sm" aria-busy={isLoading}>
                <caption className="sr-only">{caption}</caption>
                <thead className="bg-surface-subtle">
                    <tr>
                        {columns.map((column) => {
                            const isSorted = sort?.key === column.key;
                            const ariaSort = isSorted
                                ? sort?.direction === 'asc'
                                    ? 'ascending'
                                    : 'descending'
                                : column.sortable
                                  ? 'none'
                                  : undefined;

                            return (
                                <th
                                    key={column.key}
                                    scope="col"
                                    aria-sort={ariaSort}
                                    className={cn(
                                        'text-muted-foreground px-4 py-3 font-medium whitespace-nowrap',
                                        column.align === 'end' && 'text-right',
                                    )}
                                >
                                    {column.sortable && onSortChange ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onSortChange(column.key)
                                            }
                                            className={cn(
                                                'focus-visible:ring-ring inline-flex items-center gap-1 rounded-sm focus-visible:ring-2 focus-visible:outline-none',
                                                column.align === 'end' &&
                                                    'flex-row-reverse',
                                            )}
                                        >
                                            {column.header}
                                            {isSorted ? (
                                                sort?.direction === 'asc' ? (
                                                    <ChevronUp
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <ChevronDown
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                )
                                            ) : (
                                                <ChevronsUpDown
                                                    className="text-muted-foreground/50 size-3.5"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </button>
                                    ) : (
                                        column.header
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody className="divide-border-subtle divide-y">
                    {isLoading &&
                        Array.from({ length: 5 }, (_, rowIndex) => (
                            <tr key={`skeleton-${rowIndex}`}>
                                {columns.map((column) => (
                                    <td key={column.key} className="px-4 py-3">
                                        <Skeleton />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    {!isLoading && error && (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="px-4 py-6 text-center"
                            >
                                <p
                                    role="alert"
                                    className="text-destructive text-sm"
                                >
                                    {error}
                                </p>
                                {onRetry && retryLabel && (
                                    <button
                                        type="button"
                                        onClick={onRetry}
                                        className="text-primary focus-visible:ring-ring mt-2 text-sm underline underline-offset-4 focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        {retryLabel}
                                    </button>
                                )}
                            </td>
                        </tr>
                    )}
                    {showEmpty && (
                        <tr>
                            <td colSpan={columns.length} className="px-4 py-6">
                                {emptyState}
                            </td>
                        </tr>
                    )}
                    {!isLoading &&
                        !error &&
                        rows.map((row) => (
                            <tr
                                key={rowKey(row)}
                                className="hover:bg-surface-subtle/60"
                            >
                                {columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={cn(
                                            'text-foreground px-4 py-3',
                                            column.align === 'end' &&
                                                'text-right',
                                        )}
                                    >
                                        {column.render(row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                </tbody>
            </table>
        </div>
    );
}
