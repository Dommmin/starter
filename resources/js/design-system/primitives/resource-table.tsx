import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ActionMenu, type ActionMenuItem } from './action-menu';
import { Button } from './button';
import {
    DataTable,
    type DataTableColumn,
    type DataTableColumnPriority,
    type DataTableSortLabels,
} from './data-table';
import { EmptyState } from './empty-state';
import { FilterBar } from './filter-bar';
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

export type ResourceTableFilter = {
    /** Query-string key; must match a server-side filter name. */
    name: string;
    label: string;
    options: SelectFieldOption[];
    /** Value restored by "clear filters"; same as the server default. */
    defaultValue: string;
};

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
}: ResourceTableProps<Row>) {
    const [isLoading, setIsLoading] = useState(false);
    const [hasError, setHasError] = useState(false);

    const filterValues = Object.fromEntries(
        filterFields.map((field) => [
            field.name,
            filters[field.name] ?? field.defaultValue,
        ]),
    );

    function visit(change: QueryChange, replace = false) {
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
        filterFields.some(
            (field) => filterValues[field.name] !== field.defaultValue,
        );

    function clearFilters() {
        visit({
            search: '',
            filters: Object.fromEntries(
                filterFields.map((field) => [field.name, field.defaultValue]),
            ),
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
                    {filterFields.map((field) => (
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
                    ))}
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
