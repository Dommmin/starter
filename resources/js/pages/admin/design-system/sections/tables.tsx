import { FileText, Pencil, Plus, SearchX, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    ActionMenu,
    Avatar,
    Badge,
    Button,
    ConfirmDialog,
    DataTable,
    EmptyState,
    FilterBar,
    Paginator,
    ResourceTable,
    SearchInput,
    SelectField,
    Stack,
    type DataTableColumn,
    type DataTableColumnPriority,
    type DataTableSort,
    type DataTableSortDirection,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { tableSortLabels } from '@/lib/table-sort-labels';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

type ArticleStatus = 'published' | 'draft' | 'archived';

/** Synthetic article row; nothing is read from or sent to the server. */
type DemoArticle = {
    id: number;
    title: string;
    author: string;
    initials: string;
    avatar?: string;
    status: ArticleStatus;
    updatedAt: string;
    views: number;
};

/** An existing public asset stands in for a profile photo. */
const demoAvatarSrc = '/apple-touch-icon.png';
/** The showcase itself is the list URL, so ResourceTable visits stay here. */
const showcaseUrl = '/admin/design-system';

const statusTones = {
    published: 'success',
    draft: 'neutral',
    archived: 'outline',
} as const;

function useDemoArticles(): DemoArticle[] {
    const { t } = useTranslation();
    const text = (key: string) => t(`admin.designSystem.tables.rows.${key}`);

    return [
        {
            id: 1,
            title: text('title1'),
            author: 'Zofia Łęcka',
            initials: 'ZŁ',
            avatar: demoAvatarSrc,
            status: 'published',
            updatedAt: '2026-09-28T10:15:00Z',
            views: 12840,
        },
        {
            id: 2,
            title: text('title2'),
            author: 'Jürgen Weiß',
            initials: 'JW',
            status: 'draft',
            updatedAt: '2026-09-29T08:00:00Z',
            views: 0,
        },
        {
            id: 3,
            title: text('title3'),
            author: 'Anna Nowak',
            initials: 'AN',
            avatar: demoAvatarSrc,
            status: 'archived',
            updatedAt: '2025-12-01T14:30:00Z',
            views: 3905,
        },
    ];
}

function useLongArticle(): DemoArticle {
    const { t } = useTranslation();

    return {
        id: 99,
        title: t('admin.designSystem.tables.rows.longTitle'),
        author: 'Maximiliane Konstantina Wiśniewska-Grünberg von Hohenstein',
        initials: 'MW',
        status: 'draft',
        updatedAt: '2026-09-30T23:59:00Z',
        views: 1234567,
    };
}

function sortArticles(
    rows: DemoArticle[],
    sort: DataTableSort | null,
): DemoArticle[] {
    if (!sort) {
        return rows;
    }

    const factor = sort.direction === 'asc' ? 1 : -1;
    const value = (row: DemoArticle) =>
        sort.key === 'views'
            ? row.views
            : sort.key === 'updatedAt'
              ? row.updatedAt
              : row.title;

    return [...rows].sort((left, right) => {
        const a = value(left);
        const b = value(right);

        if (typeof a === 'number' && typeof b === 'number') {
            return (a - b) * factor;
        }

        return String(a).localeCompare(String(b)) * factor;
    });
}

/** Next sort after a header click: same column flips, a new one starts asc. */
function nextSort(
    current: DataTableSort | null,
    key: string,
    direction?: DataTableSortDirection,
): DataTableSort {
    return {
        key,
        direction:
            direction ??
            (current?.key === key && current.direction === 'asc'
                ? 'desc'
                : 'asc'),
    };
}

/**
 * Columns of the article demo. `withPriority` toggles the responsive roles
 * (cards below `md`, optional columns hidden in a narrow container).
 */
function useArticleColumns({
    withPriority = true,
    onDelete,
}: {
    withPriority?: boolean;
    onDelete?: (row: DemoArticle) => void;
}): DataTableColumn<DemoArticle>[] {
    const { t, formatDate, formatNumber } = useTranslation();
    const demo = (key: string, params?: Record<string, string>) =>
        t(`admin.designSystem.tables.${key}`, params);
    const role = (priority: DataTableColumnPriority) =>
        withPriority ? priority : undefined;

    const columns: DataTableColumn<DemoArticle>[] = [
        {
            key: 'title',
            header: demo('columns.title'),
            sortable: true,
            priority: role('primary'),
            render: (row) => row.title,
        },
        {
            key: 'author',
            header: demo('columns.author'),
            priority: role('secondary'),
            render: (row) => (
                <Stack gap="tight" align="start">
                    <Avatar
                        name={row.author}
                        src={row.avatar}
                        fallback={row.initials}
                        size="sm"
                    />
                    {row.author}
                </Stack>
            ),
        },
        {
            key: 'status',
            header: demo('columns.status'),
            priority: role('status'),
            render: (row) => (
                <Badge tone={statusTones[row.status]}>
                    {demo(`statuses.${row.status}`)}
                </Badge>
            ),
        },
        {
            key: 'updatedAt',
            header: demo('columns.updatedAt'),
            sortable: true,
            priority: role('optional'),
            render: (row) =>
                formatDate(row.updatedAt, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                    timeZone: 'UTC',
                }),
        },
        {
            key: 'views',
            header: demo('columns.views'),
            sortable: true,
            align: 'end',
            priority: role('card'),
            render: (row) => formatNumber(row.views),
        },
    ];

    if (onDelete) {
        columns.push({
            key: 'actions',
            header: '',
            align: 'end',
            priority: role('actions'),
            render: (row) => (
                <ActionMenu
                    triggerLabel={demo('rowActions', { title: row.title })}
                    items={[
                        {
                            id: 'edit',
                            label: demo('edit'),
                            icon: Pencil,
                            onSelect: () => {},
                        },
                        {
                            id: 'delete',
                            label: demo('delete'),
                            icon: Trash2,
                            tone: 'destructive',
                            onSelect: () => onDelete(row),
                        },
                    ]}
                />
            ),
        });
    }

    return columns;
}

/**
 * Local list: sortable headers (select in the card layout), a row menu whose
 * "Delete" asks for confirmation and removes the row, and an empty state
 * whose CTA restores the demo data.
 */
function InteractiveArticleTable({
    initialSort,
}: {
    initialSort: DataTableSort;
}) {
    const { t } = useTranslation();
    const demo = (key: string, params?: Record<string, string>) =>
        t(`admin.designSystem.tables.${key}`, params);
    const articles = useDemoArticles();
    const [removedIds, setRemovedIds] = useState<number[]>([]);
    const [sort, setSort] = useState<DataTableSort>(initialSort);
    const [toDelete, setToDelete] = useState<DemoArticle | null>(null);
    const columns = useArticleColumns({ onDelete: setToDelete });
    const rows = sortArticles(
        articles.filter((row) => !removedIds.includes(row.id)),
        sort,
    );

    return (
        <>
            <DataTable<DemoArticle>
                caption={demo('caption')}
                columns={columns}
                rows={rows}
                rowKey={(row) => row.id}
                sort={sort}
                onSortChange={(key, direction) =>
                    setSort((current) => nextSort(current, key, direction))
                }
                sortLabels={tableSortLabels(t)}
                emptyState={
                    <EmptyState
                        icon={FileText}
                        title={demo('emptyTitle')}
                        description={demo('restoreDescription')}
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setRemovedIds([])}
                            >
                                {demo('restoreRows')}
                            </Button>
                        }
                    />
                }
            />
            <ConfirmDialog
                open={toDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setToDelete(null);
                    }
                }}
                tone="destructive"
                title={demo('deleteTitle')}
                description={demo('deleteDescription', {
                    title: toDelete?.title ?? '',
                })}
                confirmLabel={demo('delete')}
                cancelLabel={demo('cancel')}
                closeLabel={demo('close')}
                onConfirm={() => {
                    if (toDelete) {
                        setRemovedIds((ids) => [...ids, toDelete.id]);
                    }

                    setToDelete(null);
                }}
            />
        </>
    );
}

/** Static list states: loading, empty with CTA, error with retry, long content. */
function StaticArticleTable({
    rows,
    isLoading = false,
    error,
    withPriority = true,
}: {
    rows: DemoArticle[];
    isLoading?: boolean;
    error?: string;
    withPriority?: boolean;
}) {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.tables.${key}`);
    const columns = useArticleColumns({ withPriority });
    const [isRetrying, setRetrying] = useState(false);

    return (
        <DataTable<DemoArticle>
            caption={demo('caption')}
            columns={columns}
            rows={rows}
            rowKey={(row) => row.id}
            isLoading={isLoading || isRetrying}
            error={isRetrying ? undefined : error}
            retryLabel={demo('retry')}
            onRetry={() => {
                setRetrying(true);
                window.setTimeout(() => setRetrying(false), 1500);
            }}
            emptyState={
                <EmptyState
                    icon={FileText}
                    title={demo('emptyTitle')}
                    description={demo('emptyDescription')}
                    action={
                        <Button size="sm" href="#adm-09">
                            <Plus aria-hidden="true" />
                            {demo('createArticle')}
                        </Button>
                    }
                />
            }
        />
    );
}

/**
 * FilterBar + SearchInput + SelectField driving a local DataTable. Starts
 * with a search that matches nothing, so the "no results" state shows.
 */
function FilteredArticleTable() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.tables.${key}`);
    const articles = useDemoArticles();
    const columns = useArticleColumns({});
    const initialSearch = demo('noMatchSearch');
    const [search, setSearch] = useState(initialSearch);
    const [status, setStatus] = useState('all');
    const hasActiveFilters = search !== '' || status !== 'all';
    const clearFilters = () => {
        setSearch('');
        setStatus('all');
    };
    const rows = articles.filter(
        (row) =>
            (status === 'all' || row.status === status) &&
            row.title.toLocaleLowerCase().includes(search.toLocaleLowerCase()),
    );

    return (
        <Stack gap="default">
            <FilterBar
                actions={
                    hasActiveFilters ? (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={clearFilters}
                        >
                            {demo('clearFilters')}
                        </Button>
                    ) : undefined
                }
            >
                <SearchInput
                    name="demo-article-search"
                    label={demo('searchLabel')}
                    placeholder={demo('searchPlaceholder')}
                    clearLabel={demo('searchClear')}
                    value={search}
                    onChange={setSearch}
                />
                <SelectField
                    name="demo-article-status"
                    label={demo('columns.status')}
                    value={status}
                    onChange={setStatus}
                    options={[
                        { value: 'all', label: demo('statuses.all') },
                        {
                            value: 'published',
                            label: demo('statuses.published'),
                        },
                        { value: 'draft', label: demo('statuses.draft') },
                        { value: 'archived', label: demo('statuses.archived') },
                    ]}
                />
            </FilterBar>
            <DataTable<DemoArticle>
                caption={demo('caption')}
                columns={columns}
                rows={rows}
                rowKey={(row) => row.id}
                emptyState={
                    <EmptyState
                        icon={SearchX}
                        title={demo('noResultsTitle')}
                        description={demo('noResultsDescription')}
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearFilters}
                            >
                                {demo('clearFilters')}
                            </Button>
                        }
                    />
                }
            />
        </Stack>
    );
}

function DemoPaginator({
    initialPage,
    totalPages,
}: {
    initialPage: number;
    totalPages: number;
}) {
    const { t } = useTranslation();
    const [page, setPage] = useState(initialPage);

    return (
        <Paginator
            page={page}
            totalPages={totalPages}
            onPageChange={setPage}
            previousLabel={t('admin.designSystem.tables.previousPage')}
            nextLabel={t('admin.designSystem.tables.nextPage')}
            summary={t('admin.designSystem.tables.pageSummary', {
                page: String(page),
                total: String(totalPages),
            })}
        />
    );
}

/**
 * ResourceTable keeps its state in the URL: every change is an Inertia GET
 * to `url`. Here the URL is the showcase itself, so the visit returns the
 * same static props; the full flow runs on /admin/users.
 */
function DemoResourceTable({
    rows,
    search = '',
    page = 1,
    total,
    withHeader = false,
    withEmptyAction = false,
}: {
    rows: DemoArticle[];
    search?: string;
    page?: number;
    total: number;
    /** PageHeader renders an `h1`; shown once in the section. */
    withHeader?: boolean;
    /** Empty-list CTA (`emptyAction`), independent of the header. */
    withEmptyAction?: boolean;
}) {
    const { t } = useTranslation();
    const demo = (key: string, params?: Record<string, string>) =>
        t(`admin.designSystem.tables.${key}`, params);
    const perPage = 3;
    const header = {
        title: demo('resourceTitle'),
        description: demo('resourceDescription'),
        actions: (
            <Button href="#adm-09">
                <Plus aria-hidden="true" />
                {demo('createArticle')}
            </Button>
        ),
    };

    return (
        <ResourceTable<DemoArticle>
            url={showcaseUrl}
            header={withHeader ? header : undefined}
            emptyAction={
                withEmptyAction ? (
                    <Button href="#adm-09">
                        <Plus aria-hidden="true" />
                        {demo('createArticle')}
                    </Button>
                ) : undefined
            }
            rows={rows}
            rowKey={(row) => row.id}
            pagination={{
                page,
                perPage,
                total,
                totalPages: Math.max(1, Math.ceil(total / perPage)),
            }}
            filters={{
                search,
                sort: 'title',
                direction: 'asc',
                status: 'all',
            }}
            searchable
            labels={{
                caption: demo('caption'),
                sort: tableSortLabels(t),
                searchLabel: demo('searchLabel'),
                searchPlaceholder: demo('searchPlaceholder'),
                searchClear: demo('searchClear'),
                clearFilters: demo('clearFilters'),
                emptyTitle: demo('emptyTitle'),
                emptyDescription: demo('emptyDescription'),
                noResultsTitle: demo('noResultsTitle'),
                noResultsDescription: demo('noResultsDescription'),
                errorTitle: demo('errorTitle'),
                errorRetry: demo('retry'),
                previousPage: demo('previousPage'),
                nextPage: demo('nextPage'),
                paginationSummary: (range) =>
                    demo('rangeSummary', {
                        from: String(range.from),
                        to: String(range.to),
                        total: String(range.total),
                    }),
            }}
            filterFields={[
                {
                    name: 'status',
                    label: demo('columns.status'),
                    defaultValue: 'all',
                    options: [
                        { value: 'all', label: demo('statuses.all') },
                        {
                            value: 'published',
                            label: demo('statuses.published'),
                        },
                        { value: 'draft', label: demo('statuses.draft') },
                    ],
                },
            ]}
            columns={[
                {
                    key: 'title',
                    label: demo('columns.title'),
                    sortable: true,
                    priority: 'primary',
                    render: (row) => row.title,
                },
                {
                    key: 'status',
                    label: demo('columns.status'),
                    priority: 'status',
                    render: (row) => (
                        <Badge tone={statusTones[row.status]}>
                            {demo(`statuses.${row.status}`)}
                        </Badge>
                    ),
                },
                {
                    key: 'author',
                    label: demo('columns.author'),
                    priority: 'secondary',
                    render: (row) => row.author,
                },
            ]}
            rowActions={{
                label: (row) => demo('rowActions', { title: row.title }),
                items: () => [
                    {
                        id: 'edit',
                        label: demo('edit'),
                        icon: Pencil,
                        onSelect: () => {},
                    },
                ],
            }}
        />
    );
}

/** ADM-09 — tables: DataTable, Paginator, SearchInput/FilterBar, ResourceTable. */
function TablesSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.tables.${key}`);
    const articles = useDemoArticles();
    const longArticle = useLongArticle();
    const noMediaRows = articles.map((row) => ({ ...row, avatar: undefined }));

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="DataTable"
                layout="full"
                notApplicable={['disabled', 'success']}
            >
                <ShowcaseState
                    state="sortAscending"
                    detail={demo('sortDetail')}
                    fill
                >
                    <InteractiveArticleTable
                        initialSort={{ key: 'title', direction: 'asc' }}
                    />
                </ShowcaseState>
                <ShowcaseState state="sortDescending" fill>
                    <InteractiveArticleTable
                        initialSort={{ key: 'views', direction: 'desc' }}
                    />
                </ShowcaseState>
                <ShowcaseState state="loading" fill>
                    <StaticArticleTable rows={[]} isLoading />
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <StaticArticleTable rows={[]} />
                </ShowcaseState>
                <ShowcaseState state="emptyFiltered" fill>
                    <FilteredArticleTable />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <StaticArticleTable rows={[]} error={demo('errorTitle')} />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <StaticArticleTable rows={[longArticle, ...articles]} />
                </ShowcaseState>
                <ShowcaseState state="noMedia" fill>
                    <StaticArticleTable rows={noMediaRows} />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="DataTable (@container)"
                layout="compact"
                notApplicable={['disabled', 'success']}
            >
                <ShowcaseState
                    state="narrowContainer"
                    detail={demo('narrowDetail')}
                    fill
                >
                    <StaticArticleTable rows={[longArticle, ...articles]} />
                </ShowcaseState>
                <ShowcaseState
                    state="withoutPriority"
                    detail={demo('withoutPriorityDetail')}
                    fill
                >
                    <StaticArticleTable rows={articles} withPriority={false} />
                </ShowcaseState>
                <ShowcaseState
                    state="sortDescending"
                    detail={demo('sortHiddenDetail')}
                    fill
                >
                    <InteractiveArticleTable
                        initialSort={{ key: 'views', direction: 'desc' }}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Paginator"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="default" fill>
                    <DemoPaginator initialPage={1} totalPages={3} />
                </ShowcaseState>
                <ShowcaseState state="manyPages" fill>
                    <DemoPaginator initialPage={7} totalPages={24} />
                </ShowcaseState>
                <ShowcaseState
                    state="disabled"
                    detail={demo('singlePage')}
                    fill
                >
                    <DemoPaginator initialPage={1} totalPages={1} />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="SearchInput · FilterBar"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'noMedia']}
            >
                <ShowcaseState state="default" fill>
                    <FilterBar>
                        <SearchInput
                            name="demo-search-default"
                            label={demo('searchLabel')}
                            placeholder={demo('searchPlaceholder')}
                            clearLabel={demo('searchClear')}
                            value=""
                            onChange={() => {}}
                        />
                    </FilterBar>
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <SearchInput
                        name="demo-search-disabled"
                        label={demo('searchLabel')}
                        placeholder={demo('searchPlaceholder')}
                        clearLabel={demo('searchClear')}
                        value=""
                        onChange={() => {}}
                        disabled
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <SearchInput
                        name="demo-search-long"
                        label={demo('searchLabel')}
                        clearLabel={demo('searchClear')}
                        value={longArticle.title}
                        onChange={() => {}}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="ResourceTable"
                layout="full"
                notApplicable={['disabled', 'success']}
            >
                <ShowcaseState
                    state="default"
                    detail={demo('resourceDetail')}
                    fill
                >
                    <DemoResourceTable
                        rows={articles}
                        page={2}
                        total={14}
                        withHeader
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="empty"
                    detail={demo('emptyActionDetail')}
                    fill
                >
                    <DemoResourceTable rows={[]} total={0} withEmptyAction />
                </ShowcaseState>
                <ShowcaseState state="emptyFiltered" fill>
                    <DemoResourceTable
                        rows={[]}
                        total={0}
                        search={demo('noMatchSearch')}
                        withEmptyAction
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const tablesFamily: ShowcaseFamily = {
    id: 'adm-09',
    titleKey: 'admin.designSystem.tables.title',
    descriptionKey: 'admin.designSystem.tables.description',
    Component: TablesSection,
};
