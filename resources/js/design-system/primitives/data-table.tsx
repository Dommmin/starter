import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';
import { useId, useSyncExternalStore, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { SelectField } from './select-field';
import { Skeleton } from './skeleton';

/**
 * Responsive role of a column. Setting `priority` on at least one column
 * switches the table to a card list below the `md` breakpoint:
 *
 * - `primary`   — card title (the row name/link); in the table the cell may
 *                 wrap but keeps a readable minimum width.
 * - `status`    — badge row under the card title.
 * - `actions`   — trailing row menu of the card.
 * - `secondary` — label–value pair in the card (default for columns without
 *                 `priority` once any column declares one).
 * - `optional`  — hidden in the card; in the table hidden while the table
 *                 container is narrower than 42rem (e.g. 768 px with the
 *                 expanded sidebar), so the title does not wrap word by word.
 *
 * Without any `priority` the table renders exactly as before (a plain table
 * with horizontal scroll inside its own container).
 */
export type DataTableColumnPriority =
    | 'primary'
    | 'status'
    | 'actions'
    | 'secondary'
    | 'optional';

export type DataTableColumn<Row> = {
    key: string;
    /** Translated column header; also the label of the card label–value pair. */
    header: string;
    render: (row: Row) => ReactNode;
    sortable?: boolean;
    align?: 'start' | 'end';
    priority?: DataTableColumnPriority;
};

export type DataTableSortDirection = 'asc' | 'desc';

export type DataTableSort = {
    key: string;
    direction: DataTableSortDirection;
};

/** Translated labels of the sort select shown in the card layout. */
export type DataTableSortLabels = {
    /** Select label, e.g. "Sort by". */
    label: string;
    /** Option text for a column and direction, e.g. "Title, ascending". */
    option: (column: string, direction: DataTableSortDirection) => string;
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
     * decides the next direction (this component is presentation-only). The
     * card-layout sort select also passes the explicitly chosen direction.
     */
    onSortChange?: (key: string, direction?: DataTableSortDirection) => void;
    /** Required for the sort select of the card layout (columns with `priority`). */
    sortLabels?: DataTableSortLabels;
    isLoading?: boolean;
    /** Translated error message; when set, an error row replaces the body. */
    error?: string;
    retryLabel?: string;
    onRetry?: () => void;
    /** Rendered instead of rows when `rows` is empty and there is no error/loading. */
    emptyState: ReactNode;
};

/** Below Tailwind `md` (48rem); must stay in sync with the `md:` classes below. */
const CARD_LAYOUT_QUERY = '(max-width: 47.99rem)';

type Layout = 'table' | 'cards' | 'both';

function canMatchMedia(): boolean {
    return (
        typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    );
}

function subscribeToLayout(onChange: () => void): () => void {
    if (!canMatchMedia()) {
        return () => {};
    }

    const query = window.matchMedia(CARD_LAYOUT_QUERY);
    query.addEventListener('change', onChange);

    return () => query.removeEventListener('change', onChange);
}

function clientLayout(): Layout {
    if (!canMatchMedia()) {
        return 'table';
    }

    return window.matchMedia(CARD_LAYOUT_QUERY).matches ? 'cards' : 'table';
}

/**
 * SSR (and hydration) cannot know the viewport, so both layouts are rendered
 * and CSS shows the right one; the client then keeps only the matching one.
 */
function serverLayout(): Layout {
    return 'both';
}

function useResponsiveLayout(isResponsive: boolean): Layout {
    const layout = useSyncExternalStore(
        subscribeToLayout,
        clientLayout,
        serverLayout,
    );

    return isResponsive ? layout : 'table';
}

function SortIcon({
    isSorted,
    direction,
}: {
    isSorted: boolean;
    direction: DataTableSortDirection | undefined;
}) {
    if (!isSorted) {
        return (
            <ChevronsUpDown
                className="text-muted-foreground/50 size-3.5"
                aria-hidden="true"
            />
        );
    }

    return direction === 'asc' ? (
        <ChevronUp className="size-3.5" aria-hidden="true" />
    ) : (
        <ChevronDown className="size-3.5" aria-hidden="true" />
    );
}

function ErrorMessage({
    error,
    retryLabel,
    onRetry,
}: {
    error: string;
    retryLabel?: string;
    onRetry?: () => void;
}) {
    return (
        <>
            <p role="alert" className="text-destructive text-sm">
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
        </>
    );
}

export function DataTable<Row>({
    caption,
    columns,
    rows,
    rowKey,
    sort = null,
    onSortChange,
    sortLabels,
    isLoading = false,
    error,
    retryLabel,
    onRetry,
    emptyState,
}: DataTableProps<Row>) {
    const isResponsive = columns.some((column) => column.priority);
    const layout = useResponsiveLayout(isResponsive);
    const showEmpty = !isLoading && !error && rows.length === 0;

    const table = (
        <DataTableGrid
            caption={caption}
            columns={columns}
            rows={rows}
            rowKey={rowKey}
            sort={sort}
            onSortChange={onSortChange}
            isLoading={isLoading}
            error={error}
            retryLabel={retryLabel}
            onRetry={onRetry}
            emptyState={emptyState}
            showEmpty={showEmpty}
        />
    );

    if (layout === 'table') {
        return table;
    }

    const cards = (
        <DataTableCards
            caption={caption}
            columns={columns}
            rows={rows}
            rowKey={rowKey}
            sort={sort}
            onSortChange={onSortChange}
            sortLabels={sortLabels}
            isLoading={isLoading}
            error={error}
            retryLabel={retryLabel}
            onRetry={onRetry}
            emptyState={emptyState}
            showEmpty={showEmpty}
        />
    );

    if (layout === 'cards') {
        return cards;
    }

    return (
        <>
            <div className="md:hidden">{cards}</div>
            <div className="hidden md:block">{table}</div>
        </>
    );
}

type LayoutProps<Row> = Omit<DataTableProps<Row>, 'sort'> & {
    sort: DataTableSort | null;
    showEmpty: boolean;
};

function DataTableGrid<Row>({
    caption,
    columns,
    rows,
    rowKey,
    sort,
    onSortChange,
    isLoading = false,
    error,
    retryLabel,
    onRetry,
    emptyState,
    showEmpty,
}: LayoutProps<Row>) {
    /**
     * Optional columns yield to the title when the table container is narrow;
     * titles and secondary values (e.g. long e-mails) wrap instead of forcing
     * horizontal scroll, the title keeping a readable minimum width.
     */
    const cellVisibility = (column: DataTableColumn<Row>) =>
        cn(
            column.priority === 'optional' && 'hidden @2xl:table-cell',
            column.priority === 'primary' && 'min-w-32 wrap-anywhere',
            column.priority === 'secondary' && 'min-w-32 wrap-anywhere',
        );

    return (
        <div className="border-border-subtle @container overflow-x-auto rounded-lg border">
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
                                        column.priority === 'optional' &&
                                            'hidden @2xl:table-cell',
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
                                            <SortIcon
                                                isSorted={isSorted}
                                                direction={sort?.direction}
                                            />
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
                                    <td
                                        key={column.key}
                                        className={cn(
                                            'px-4 py-3',
                                            cellVisibility(column),
                                        )}
                                    >
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
                                <ErrorMessage
                                    error={error}
                                    retryLabel={retryLabel}
                                    onRetry={onRetry}
                                />
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
                                            cellVisibility(column),
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

function DataTableCards<Row>({
    caption,
    columns,
    rows,
    rowKey,
    sort,
    onSortChange,
    sortLabels,
    isLoading = false,
    error,
    retryLabel,
    onRetry,
    emptyState,
    showEmpty,
}: LayoutProps<Row>) {
    const idPrefix = useId();
    const primary =
        columns.find((column) => column.priority === 'primary') ?? columns[0];
    const statuses = columns.filter((column) => column.priority === 'status');
    const actions = columns.filter((column) => column.priority === 'actions');
    const details = columns.filter(
        (column) =>
            column !== primary &&
            (column.priority === undefined || column.priority === 'secondary'),
    );
    const sortableColumns = columns.filter((column) => column.sortable);
    const showSort =
        onSortChange !== undefined &&
        sortLabels !== undefined &&
        sortableColumns.length > 0;

    return (
        <div className="flex flex-col gap-3">
            {showSort && (
                <SelectField
                    name="sort"
                    label={sortLabels.label}
                    value={sort ? `${sort.key}:${sort.direction}` : ''}
                    onChange={(value) => {
                        const [key, direction] = value.split(':');
                        onSortChange(
                            key,
                            direction === 'desc' ? 'desc' : 'asc',
                        );
                    }}
                    options={sortableColumns.flatMap((column) =>
                        (['asc', 'desc'] as const).map((direction) => ({
                            value: `${column.key}:${direction}`,
                            label: sortLabels.option(column.header, direction),
                        })),
                    )}
                />
            )}

            {!isLoading && error && (
                <div className="border-border-subtle rounded-lg border px-4 py-6 text-center">
                    <ErrorMessage
                        error={error}
                        retryLabel={retryLabel}
                        onRetry={onRetry}
                    />
                </div>
            )}

            {showEmpty && (
                <div className="border-border-subtle rounded-lg border px-4 py-6">
                    {emptyState}
                </div>
            )}

            {(isLoading || (!error && rows.length > 0)) && (
                <ul
                    aria-label={caption}
                    aria-busy={isLoading}
                    className="border-border-subtle divide-border-subtle divide-y rounded-lg border"
                >
                    {isLoading
                        ? Array.from({ length: 5 }, (_, index) => (
                              <li
                                  key={`skeleton-${index}`}
                                  className="flex flex-col gap-2 p-4"
                              >
                                  <Skeleton />
                                  <Skeleton />
                              </li>
                          ))
                        : rows.map((row, index) => {
                              const titleId = `${idPrefix}-title-${index}`;

                              return (
                                  <li key={rowKey(row)}>
                                      <article
                                          aria-labelledby={titleId}
                                          className="flex flex-col gap-2 p-4 text-sm"
                                      >
                                          <div className="flex items-start gap-3">
                                              <div
                                                  id={titleId}
                                                  className="text-foreground min-w-0 flex-1 font-medium break-words"
                                              >
                                                  {primary?.render(row)}
                                              </div>
                                              {actions.map((column) => (
                                                  <div
                                                      key={column.key}
                                                      className="-my-1 shrink-0"
                                                  >
                                                      {column.render(row)}
                                                  </div>
                                              ))}
                                          </div>
                                          {statuses.length > 0 && (
                                              <div className="flex flex-wrap items-center gap-2">
                                                  {statuses.map((column) => (
                                                      <div key={column.key}>
                                                          {column.render(row)}
                                                      </div>
                                                  ))}
                                              </div>
                                          )}
                                          {details.length > 0 && (
                                              <dl className="grid grid-cols-[minmax(0,auto)_minmax(0,1fr)] gap-x-3 gap-y-1">
                                                  {details.map((column) => (
                                                      <div
                                                          key={column.key}
                                                          className="col-span-2 grid grid-cols-subgrid"
                                                      >
                                                          <dt className="text-muted-foreground">
                                                              {column.header}
                                                          </dt>
                                                          <dd className="text-foreground wrap-anywhere">
                                                              {column.render(
                                                                  row,
                                                              )}
                                                          </dd>
                                                      </div>
                                                  ))}
                                              </dl>
                                          )}
                                      </article>
                                  </li>
                              );
                          })}
                </ul>
            )}
        </div>
    );
}
