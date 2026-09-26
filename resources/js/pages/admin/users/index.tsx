import { Head, router, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import {
    ActionMenu,
    Badge,
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    Paginator,
    PageHeader,
    RetryPanel,
    SearchInput,
    SelectField,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    createdAt: string | null;
};

type VerifiedFilter = 'all' | 'verified' | 'unverified';
type SortKey = 'name' | 'email' | 'created_at';
type SortDirection = 'asc' | 'desc';

type Filters = {
    search: string;
    verified: VerifiedFilter;
    sort: SortKey;
    direction: SortDirection;
};

type Pagination = {
    page: number;
    totalPages: number;
    total: number;
    perPage: number;
};

type AdminUsersPageProps = {
    users: UserRow[];
    pagination: Pagination;
    filters: Filters;
};

export default function AdminUsersIndex() {
    const { users, pagination, filters } = usePage<AdminUsersPageProps>().props;
    const { t, formatDate } = useTranslation();
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function visit(next: Partial<Filters> & { page?: number }) {
        setError(null);
        setIsLoading(true);

        router.get(
            usersIndex().url,
            {
                search: next.search ?? filters.search,
                verified: next.verified ?? filters.verified,
                sort: next.sort ?? filters.sort,
                direction: next.direction ?? filters.direction,
                page: next.page ?? 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => setIsLoading(false),
                onError: () => {
                    setIsLoading(false);
                    setError(t('admin.users.errorTitle'));
                },
            },
        );
    }

    function handleSortChange(key: string) {
        const nextDirection: SortDirection =
            filters.sort === key && filters.direction === 'asc'
                ? 'desc'
                : 'asc';
        visit({ sort: key as SortKey, direction: nextDirection });
    }

    const hasActiveFilters =
        filters.search !== '' || filters.verified !== 'all';

    return (
        <>
            <Head title={t('admin.users.title')} />

            <Stack gap="default">
                <PageHeader
                    title={t('admin.users.title')}
                    description={t('admin.users.description')}
                />

                <FilterBar
                    actions={
                        hasActiveFilters ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    visit({
                                        search: '',
                                        verified: 'all',
                                        page: 1,
                                    })
                                }
                            >
                                {t('admin.users.clearFilters')}
                            </Button>
                        ) : undefined
                    }
                >
                    <SearchInput
                        name="search"
                        label={t('admin.users.searchLabel')}
                        placeholder={t('admin.users.searchPlaceholder')}
                        clearLabel={t('admin.users.searchClear')}
                        value={filters.search}
                        onChange={(search) => visit({ search })}
                    />
                    <SelectField
                        name="verified"
                        label={t('admin.users.filterVerifiedLabel')}
                        value={filters.verified}
                        onChange={(verified) =>
                            visit({ verified: verified as VerifiedFilter })
                        }
                        options={[
                            {
                                value: 'all',
                                label: t('admin.users.filterAll'),
                            },
                            {
                                value: 'verified',
                                label: t('admin.users.filterVerified'),
                            },
                            {
                                value: 'unverified',
                                label: t('admin.users.filterUnverified'),
                            },
                        ]}
                    />
                </FilterBar>

                {error ? (
                    <RetryPanel
                        title={error}
                        retryLabel={t('admin.users.errorRetry')}
                        onRetry={() => visit({})}
                    />
                ) : (
                    <>
                        <DataTable<UserRow>
                            caption={t('admin.users.tableCaption')}
                            rows={users}
                            rowKey={(row) => row.id}
                            isLoading={isLoading}
                            sort={{
                                key: filters.sort,
                                direction: filters.direction,
                            }}
                            onSortChange={handleSortChange}
                            emptyState={
                                <EmptyState
                                    title={t('admin.users.emptyTitle')}
                                    description={t(
                                        'admin.users.emptyDescription',
                                    )}
                                />
                            }
                            columns={[
                                {
                                    key: 'name',
                                    header: t('admin.users.columnName'),
                                    sortable: true,
                                    render: (row) => row.name,
                                },
                                {
                                    key: 'email',
                                    header: t('admin.users.columnEmail'),
                                    sortable: true,
                                    render: (row) => row.email,
                                },
                                {
                                    key: 'status',
                                    header: t('admin.users.columnStatus'),
                                    render: (row) => (
                                        <Badge
                                            tone={
                                                row.verified
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {row.verified
                                                ? t(
                                                      'admin.users.statusVerified',
                                                  )
                                                : t(
                                                      'admin.users.statusUnverified',
                                                  )}
                                        </Badge>
                                    ),
                                },
                                {
                                    key: 'created_at',
                                    header: t('admin.users.columnCreatedAt'),
                                    sortable: true,
                                    render: (row) =>
                                        row.createdAt
                                            ? formatDate(row.createdAt)
                                            : '—',
                                },
                                {
                                    key: 'actions',
                                    header: '',
                                    align: 'end',
                                    render: (row) => (
                                        <ActionMenu
                                            triggerLabel={t(
                                                'admin.users.rowActionsLabel',
                                                { name: row.name },
                                            )}
                                            items={[
                                                {
                                                    id: 'copy-email',
                                                    label: t(
                                                        'admin.users.actionCopyEmail',
                                                    ),
                                                    onSelect: () => {
                                                        void navigator.clipboard.writeText(
                                                            row.email,
                                                        );
                                                    },
                                                },
                                                {
                                                    id: 'email-user',
                                                    label: t(
                                                        'admin.users.actionEmailUser',
                                                    ),
                                                    icon: Mail,
                                                    onSelect: () => {
                                                        window.location.assign(
                                                            `mailto:${row.email}`,
                                                        );
                                                    },
                                                },
                                            ]}
                                        />
                                    ),
                                },
                            ]}
                        />

                        <Paginator
                            page={pagination.page}
                            totalPages={pagination.totalPages}
                            onPageChange={(page) => visit({ page })}
                            previousLabel={t('admin.users.previousPage')}
                            nextLabel={t('admin.users.nextPage')}
                            summary={t('admin.users.paginationSummary', {
                                from:
                                    pagination.total === 0
                                        ? 0
                                        : (pagination.page - 1) *
                                              pagination.perPage +
                                          1,
                                to: Math.min(
                                    pagination.page * pagination.perPage,
                                    pagination.total,
                                ),
                                total: pagination.total,
                            })}
                        />
                    </>
                )}
            </Stack>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.users.title',
            href: usersIndex(),
        },
    ],
};
