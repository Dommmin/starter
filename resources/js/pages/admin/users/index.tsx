import { Head, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import {
    Badge,
    ResourceTable,
    type ResourceListFilters,
    type ResourceListPagination,
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

type AdminUsersPageProps = {
    items: UserRow[];
    pagination: ResourceListPagination;
    filters: ResourceListFilters;
};

export default function AdminUsersIndex() {
    const { items, pagination, filters } = usePage<AdminUsersPageProps>().props;
    const { t, formatDate } = useTranslation();

    return (
        <>
            <Head title={t('admin.users.title')} />

            <ResourceTable<UserRow>
                url={usersIndex()}
                header={{
                    title: t('admin.users.title'),
                    description: t('admin.users.description'),
                }}
                rows={items}
                rowKey={(row) => row.id}
                pagination={pagination}
                filters={filters}
                searchable
                labels={{
                    caption: t('admin.users.tableCaption'),
                    searchLabel: t('admin.users.searchLabel'),
                    searchPlaceholder: t('admin.users.searchPlaceholder'),
                    searchClear: t('admin.users.searchClear'),
                    clearFilters: t('admin.users.clearFilters'),
                    emptyTitle: t('admin.users.emptyTitle'),
                    emptyDescription: t('admin.users.emptyDescription'),
                    errorTitle: t('admin.users.errorTitle'),
                    errorRetry: t('admin.users.errorRetry'),
                    previousPage: t('admin.users.previousPage'),
                    nextPage: t('admin.users.nextPage'),
                    paginationSummary: (range) =>
                        t('admin.users.paginationSummary', range),
                }}
                filterFields={[
                    {
                        name: 'verified',
                        label: t('admin.users.filterVerifiedLabel'),
                        defaultValue: 'all',
                        options: [
                            { value: 'all', label: t('admin.users.filterAll') },
                            {
                                value: 'verified',
                                label: t('admin.users.filterVerified'),
                            },
                            {
                                value: 'unverified',
                                label: t('admin.users.filterUnverified'),
                            },
                        ],
                    },
                ]}
                columns={[
                    {
                        key: 'name',
                        label: t('admin.users.columnName'),
                        sortable: true,
                        render: (row) => row.name,
                    },
                    {
                        key: 'email',
                        label: t('admin.users.columnEmail'),
                        sortable: true,
                        render: (row) => row.email,
                    },
                    {
                        key: 'status',
                        label: t('admin.users.columnStatus'),
                        render: (row) => (
                            <Badge tone={row.verified ? 'success' : 'neutral'}>
                                {row.verified
                                    ? t('admin.users.statusVerified')
                                    : t('admin.users.statusUnverified')}
                            </Badge>
                        ),
                    },
                    {
                        key: 'created_at',
                        label: t('admin.users.columnCreatedAt'),
                        sortable: true,
                        render: (row) =>
                            row.createdAt ? formatDate(row.createdAt) : '—',
                    },
                ]}
                rowActions={{
                    label: (row) =>
                        t('admin.users.rowActionsLabel', { name: row.name }),
                    items: (row) => [
                        {
                            id: 'copy-email',
                            label: t('admin.users.actionCopyEmail'),
                            onSelect: () => {
                                void navigator.clipboard.writeText(row.email);
                            },
                        },
                        {
                            id: 'email-user',
                            label: t('admin.users.actionEmailUser'),
                            icon: Mail,
                            onSelect: () => {
                                window.location.assign(`mailto:${row.email}`);
                            },
                        },
                    ],
                }}
            />
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
