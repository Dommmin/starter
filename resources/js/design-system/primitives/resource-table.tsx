import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ActionMenu, type ActionMenuItem } from './action-menu';
import { Button } from './button';
import { ConfirmDialog } from './confirm-dialog';
import {
    DataTable,
    type DataTableColumn,
    type DataTableColumnPriority,
    type DataTableSortLabels,
} from './data-table';
import { DateRangeField } from './date-range-field';
import { EmptyState } from './empty-state';
import { FilterBar } from './filter-bar';
import { Inline } from './inline';
import { PageHeader } from './page-header';
import { Paginator } from './paginator';
import { RetryPanel } from './retry-panel';
import { SearchInput } from './search-input';
import { SelectField, type SelectFieldOption } from './select-field';
import { Stack } from './stack';

export type ResourceListSortDirection = 'asc' | 'desc';

/** Pagination metadata of the uniform list payload (`App\Support\Listing\ListQuery`). */
export type ResourceListPagination = {
    page: number;
    totalPages: number;
    total: number;
    perPage: number;
};

/** Effective list state echoed by the server; filter values are keyed by filter name. */
export type ResourceListFilters = {
    search: string;
    sort: string;
    direction: ResourceListSortDirection;
    [filter: string]: string;
};

export type ResourceTableColumn<Row> = {
    key: string;
    /** Translated column header. */
    label: string;
    /** Must match a key in the server-side sort allowlist. */
    sortable?: boolean;
    align?: 'start' | 'end';
    /**
     * Responsive role (see `DataTableColumnPriority`). When any column sets it,
     * the list renders as cards below `md` and the row menu stays visible.
     */
    priority?: DataTableColumnPriority;
    render: (row: Row) => ReactNode;
};

export type ResourceTableSelectFilter = {
    kind?: 'select';
    /** Query-string key; must match a server-side filter name. */
    name: string;
    label: string;
    options: SelectFieldOption[];
    /** Value restored by "clear filters"; same as the server default. */
    defaultValue: string;
};

/**
 * Inclusive date range (`ListQuery::dateRange`): the query string carries
 * `{name}_from` and `{name}_to` as `YYYY-MM-DD`; "clear filters" empties both
 * and an inverted range is swapped before the visit.
 */
export type ResourceTableDateRangeFilter = {
    kind: 'dateRange';
    name: string;
    label: string;
    labels: { from: string; to: string };
};

export type ResourceTableFilter =
    | ResourceTableSelectFilter
    | ResourceTableDateRangeFilter;

/** Query-string keys and their "clear filters" values of one filter. */
function filterDefaults(field: ResourceTableFilter): Record<string, string> {
    return field.kind === 'dateRange'
        ? { [`${field.name}_from`]: '', [`${field.name}_to`]: '' }
        : { [field.name]: field.defaultValue };
}

export type ResourceTableRowActions<Row> = {
    /** Translated accessible label of the row menu trigger. */
    label: (row: Row) => string;
    items: (row: Row) => ActionMenuItem[];
};

export type ResourceTableLabels = {
    caption: string;
    searchLabel?: string;
    searchPlaceholder?: string;
    searchClear?: string;
    clearFilters: string;
    emptyTitle: string;
    emptyDescription?: string;
    /** Shown instead of the empty state when search/filters are active. */
    noResultsTitle?: string;
    noResultsDescription?: string;
    errorTitle: string;
    errorRetry: string;
    previousPage: string;
    nextPage: string;
    /** Sort select of the card layout; required when columns set `priority`. */
    sort?: DataTableSortLabels;
    paginationSummary: (range: {
        from: number;
        to: number;
        total: number;
    }) => string;
};

/** One action over the selected rows; always confirmed in a dialog. */
export type ResourceTableBulkAction = {
    id: string;
    label: string;
    tone?: 'default' | 'destructive';
    confirm: {
        title: string;
        description: (count: number) => string;
        confirmLabel: string;
        cancelLabel: string;
        closeLabel: string;
    };
    /**
     * Runs the action for the selected row keys (`String(rowKey(row))`).
     * Resolve after success: the dialog closes and the selection is cleared.
     * Reject to keep both, e.g. when the server refused the request; the
     * page reports the error (flash toast or inline message).
     */
    onRun: (keys: string[]) => Promise<void>;
};

/**
 * Row selection with bulk actions. Selection covers the current page only
 * and is cleared by every search, filter, sort or page change. Show it only
 * when the user may perform at least one action; the server authorizes each
 * selected record again.
 */
export type ResourceTableBulkActions<Row> = {
    /** Accessible name of the bulk action toolbar. */
    label: string;
    selectAllLabel: string;
    selectRowLabel: (row: Row) => string;
    /** Announced count, e.g. "3 selected". */
    selectedSummary: (count: number) => string;
    clearLabel: string;
    actions: ResourceTableBulkAction[];
};

export type ResourceTableProps<Row> = {
    /** List endpoint, e.g. a Wayfinder route: `usersIndex()`. */
    url: string | { url: string };
    rows: Row[];
    rowKey: (row: Row) => string | number;
    columns: ResourceTableColumn<Row>[];
    pagination: ResourceListPagination;
    filters: ResourceListFilters;
    labels: ResourceTableLabels;
    /** Render the search box; requires `labels.searchLabel` and `labels.searchClear`. */
    searchable?: boolean;
    filterFields?: ResourceTableFilter[];
    rowActions?: ResourceTableRowActions<Row>;
    header?: {
        title: string;
        description?: string;
        actions?: ReactNode;
    };
    /**
     * Call to action of the empty list (e.g. a "Create" `Button`), shown only
     * when there are no records at all — never for an empty search/filter
     * result, which offers "clear filters" instead. The caller decides
     * whether the user may perform it (e.g. `can.create`). Works without
     * `header`, so a page with its own `PageHeader` still gets the CTA.
     */
    emptyAction?: ReactNode;
    bulkActions?: ResourceTableBulkActions<Row>;
    className?: never;
    style?: never;
};

type QueryChange = {
    search?: string;
    sort?: string;
    direction?: ResourceListSortDirection;
    filters?: Record<string, string>;
    page?: number;
};

/**
 * Server-driven resource list: search, allowlisted filters, sortable columns,
 * pagination and row actions, with the whole state kept in the URL.
 *
 * State is read from `filters`/`pagination` props (so Back/Forward and reload
 * restore it); every change issues an Inertia GET that resets `page` to 1
 * unless the change is itself a page change. Typing in search replaces the
 * history entry; sort, filter and page changes push one.
 */
export function ResourceTable<Row>({
    url,
    rows,
    rowKey,
    columns,
    pagination,
    filters,
    labels,
    searchable = false,
    filterFields = [],
    rowActions,
    header,
    emptyAction,
    bulkActions,
}: ResourceTableProps<Row>) {
    const [isLoading, setIsLoading] = useState(false);
    const [hasError, setHasError] = useState(false);
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(
        () => new Set(),
    );
    const [confirming, setConfirming] =
        useState<ResourceTableBulkAction | null>(null);
    const [isRunning, setIsRunning] = useState(false);

    // Rows removed by a reload (e.g. after a bulk delete) drop out of the
    // selection without an effect: only keys of the current rows count.
    const rowKeys = new Set(rows.map((row) => String(rowKey(row))));
    const selection = new Set(
        [...selectedKeys].filter((key) => rowKeys.has(key)),
    );

    const filterDefaultValues: Record<string, string> = Object.assign(
        {},
        ...filterFields.map(filterDefaults),
    );
    const filterValues = Object.fromEntries(
        Object.entries(filterDefaultValues).map(([key, defaultValue]) => [
            key,
            filters[key] ?? defaultValue,
        ]),
    );

    function visit(change: QueryChange, replace = false) {
        setSelectedKeys(new Set());
        setHasError(false);
        setIsLoading(true);

        const fail = () => setHasError(true);

        router.get(
            typeof url === 'string' ? url : url.url,
            {
                ...(searchable
                    ? { search: change.search ?? filters.search }
                    : {}),
                ...filterValues,
                ...change.filters,
                sort: change.sort ?? filters.sort,
                direction: change.direction ?? filters.direction,
                page: change.page ?? 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace,
                onFinish: () => setIsLoading(false),
                onError: fail,
                onHttpException: fail,
                onNetworkError: fail,
            },
        );
    }

    function handleSortChange(
        key: string,
        explicitDirection?: ResourceListSortDirection,
    ) {
        const direction: ResourceListSortDirection =
            explicitDirection ??
            (filters.sort === key && filters.direction === 'asc'
                ? 'desc'
                : 'asc');
        visit({ sort: key, direction });
    }

    const hasActiveFilters =
        (searchable && filters.search !== '') ||
        Object.entries(filterDefaultValues).some(
            ([key, defaultValue]) => filterValues[key] !== defaultValue,
        );

    function clearFilters() {
        visit({
            search: '',
            filters: filterDefaultValues,
        });
    }

    const tableColumns: DataTableColumn<Row>[] = columns.map((column) => ({
        key: column.key,
        header: column.label,
        sortable: column.sortable,
        align: column.align,
        priority: column.priority,
        render: column.render,
    }));
    const isResponsive = columns.some((column) => column.priority);

    if (rowActions) {
        tableColumns.push({
            key: '__actions',
            header: '',
            align: 'end',
            priority: isResponsive ? 'actions' : undefined,
            render: (row) => (
                <ActionMenu
                    triggerLabel={rowActions.label(row)}
                    items={rowActions.items(row)}
                />
            ),
        });
    }

    async function runConfirmed(): Promise<void> {
        if (!confirming) {
            return;
        }

        setIsRunning(true);

        try {
            await confirming.onRun([...selection]);
            setConfirming(null);
            setSelectedKeys(new Set());
        } catch {
            // The page reports the failure; keep the dialog and selection.
        } finally {
            setIsRunning(false);
        }
    }

    const showNoResults = hasActiveFilters && labels.noResultsTitle;
    const from =
        pagination.total === 0
            ? 0
            : (pagination.page - 1) * pagination.perPage + 1;
    const to = Math.min(pagination.page * pagination.perPage, pagination.total);

    const hasToolbar = searchable || filterFields.length > 0;

    return (
        <Stack gap="default">
            {header && (
                <PageHeader
                    title={header.title}
                    description={header.description}
                    actions={header.actions}
                />
            )}

            {hasToolbar && (
                <FilterBar
                    actions={
                        hasActiveFilters ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearFilters}
                            >
                                {labels.clearFilters}
                            </Button>
                        ) : undefined
                    }
                >
                    {searchable && (
                        <SearchInput
                            name="search"
                            label={labels.searchLabel ?? labels.caption}
                            placeholder={labels.searchPlaceholder}
                            clearLabel={
                                labels.searchClear ?? labels.clearFilters
                            }
                            value={filters.search}
                            onChange={(search) => visit({ search }, true)}
                        />
                    )}
                    {filterFields.map((field) =>
                        field.kind === 'dateRange' ? (
                            <DateRangeField
                                key={field.name}
                                name={field.name}
                                label={field.label}
                                labels={field.labels}
                                value={{
                                    from: filterValues[`${field.name}_from`],
                                    to: filterValues[`${field.name}_to`],
                                }}
                                onChange={(range) => {
                                    // A typed date can bypass the inputs'
                                    // min/max; swap an inverted range so the
                                    // list request stays valid.
                                    const [from, to] =
                                        range.from &&
                                        range.to &&
                                        range.from > range.to
                                            ? [range.to, range.from]
                                            : [range.from, range.to];

                                    visit({
                                        filters: {
                                            [`${field.name}_from`]: from,
                                            [`${field.name}_to`]: to,
                                        },
                                    });
                                }}
                            />
                        ) : (
                            <SelectField
                                key={field.name}
                                name={field.name}
                                label={field.label}
                                value={filterValues[field.name]}
                                options={field.options}
                                onChange={(value) =>
                                    visit({ filters: { [field.name]: value } })
                                }
                            />
                        ),
                    )}
                </FilterBar>
            )}

            {hasError ? (
                <RetryPanel
                    title={labels.errorTitle}
                    retryLabel={labels.errorRetry}
                    onRetry={() => visit({ page: pagination.page })}
                />
            ) : (
                <>
                    {bulkActions && (
                        <>
                            {/*
                             * Always rendered while bulk actions exist, so the
                             * first selection does not shift the table under
                             * the pointer; actions stay disabled until a row
                             * is selected.
                             */}
                            <div
                                role="toolbar"
                                aria-label={bulkActions.label}
                                className="bg-surface-subtle border-border-subtle rounded-lg border px-4 py-2"
                            >
                                <Inline gap="tight" align="center" wrap>
                                    <span
                                        role="status"
                                        aria-live="polite"
                                        className="text-foreground me-auto text-sm font-medium"
                                    >
                                        {bulkActions.selectedSummary(
                                            selection.size,
                                        )}
                                    </span>
                                    {bulkActions.actions.map((action) => (
                                        <Button
                                            key={action.id}
                                            size="sm"
                                            variant={
                                                action.tone === 'destructive'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                            disabled={selection.size === 0}
                                            onClick={() =>
                                                setConfirming(action)
                                            }
                                        >
                                            {action.label}
                                        </Button>
                                    ))}
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={selection.size === 0}
                                        onClick={() =>
                                            setSelectedKeys(new Set())
                                        }
                                    >
                                        {bulkActions.clearLabel}
                                    </Button>
                                </Inline>
                            </div>
                            {confirming && (
                                <ConfirmDialog
                                    open
                                    onOpenChange={(open) => {
                                        if (!open && !isRunning) {
                                            setConfirming(null);
                                        }
                                    }}
                                    title={confirming.confirm.title}
                                    description={confirming.confirm.description(
                                        selection.size,
                                    )}
                                    confirmLabel={
                                        confirming.confirm.confirmLabel
                                    }
                                    cancelLabel={confirming.confirm.cancelLabel}
                                    closeLabel={confirming.confirm.closeLabel}
                                    tone={confirming.tone ?? 'default'}
                                    isPending={isRunning}
                                    onConfirm={() => void runConfirmed()}
                                />
                            )}
                        </>
                    )}
                    <DataTable<Row>
                        caption={labels.caption}
                        rows={rows}
                        rowKey={rowKey}
                        isLoading={isLoading}
                        sort={{
                            key: filters.sort,
                            direction: filters.direction,
                        }}
                        onSortChange={handleSortChange}
                        sortLabels={labels.sort}
                        columns={tableColumns}
                        selection={
                            bulkActions
                                ? {
                                      selectedKeys: selection,
                                      onChange: setSelectedKeys,
                                      selectAllLabel:
                                          bulkActions.selectAllLabel,
                                      selectRowLabel:
                                          bulkActions.selectRowLabel,
                                  }
                                : undefined
                        }
                        emptyState={
                            showNoResults ? (
                                <EmptyState
                                    title={labels.noResultsTitle ?? ''}
                                    description={labels.noResultsDescription}
                                    action={
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={clearFilters}
                                        >
                                            {labels.clearFilters}
                                        </Button>
                                    }
                                />
                            ) : (
                                <EmptyState
                                    title={labels.emptyTitle}
                                    description={labels.emptyDescription}
                                    action={
                                        hasActiveFilters
                                            ? undefined
                                            : emptyAction
                                    }
                                />
                            )
                        }
                    />

                    <Paginator
                        page={pagination.page}
                        totalPages={pagination.totalPages}
                        onPageChange={(page) => visit({ page })}
                        previousLabel={labels.previousPage}
                        nextLabel={labels.nextPage}
                        summary={labels.paginationSummary({
                            from,
                            to,
                            total: pagination.total,
                        })}
                    />
                </>
            )}
        </Stack>
    );
}
