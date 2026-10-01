import { Head, router, usePage } from '@inertiajs/react';
import { Mail, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    ConfirmDialog,
    ResourceTable,
    Stack,
    type ResourceListFilters,
    type ResourceListPagination,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { tableSortLabels } from '@/lib/table-sort-labels';
import { index as adminIndex } from '@/routes/admin';
import {
    create as usersCreate,
    destroy as usersDestroy,
    edit as usersEdit,
    index as usersIndex,
} from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: App.Enums.UserRole | null;
    verified: boolean;
    isSelf: boolean;
    createdAt: string | null;
};

type AdminUsersPageProps = {
    items: UserRow[];
    pagination: ResourceListPagination;
    filters: ResourceListFilters;
    can: { create: boolean };
};

export default function AdminUsersIndex() {
    const { items, pagination, filters, can } =
        usePage<AdminUsersPageProps>().props;
    const { t, formatDate } = useTranslation();
    const [userToDelete, setUserToDelete] = useState<UserRow | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    function confirmDelete() {
        if (!userToDelete) {
            return;
        }

        setIsDeleting(true);
        setDeleteError(null);
        router.delete(usersDestroy.url(userToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setUserToDelete(null),
            onError: (received) => {
                setUserToDelete(null);
                setDeleteError(Object.values(received)[0] ?? null);
            },
            onFinish: () => setIsDeleting(false),
        });
    }

    return (
        <>
            <Head title={t('admin.users.title')} />

            {deleteError && (
                <Stack gap="default">
                    <Alert tone="danger" title={deleteError} />
                </Stack>
            )}

            <ResourceTable<UserRow>
                url={usersIndex()}
                header={{
                    title: t('admin.users.title'),
                    description: t('admin.users.description'),
                    actions: can.create ? (
                        <Button href={usersCreate()}>
                            {t('admin.users.create')}
                        </Button>
                    ) : undefined,
                }}
                emptyAction={
                    can.create ? (
                        <Button href={usersCreate()}>
                            {t('admin.users.create')}
                        </Button>
                    ) : undefined
                }
                rows={items}
                rowKey={(row) => row.id}
                pagination={pagination}
                filters={filters}
                searchable
                labels={{
                    caption: t('admin.users.tableCaption'),
                    sort: tableSortLabels(t),
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
                        priority: 'primary',
                        label: t('admin.users.columnName'),
                        sortable: true,
                        render: (row) =>
                            row.isSelf
                                ? `${row.name} (${t('admin.users.you')})`
                                : row.name,
                    },
                    {
                        key: 'email',
                        priority: 'card',
                        label: t('admin.users.columnEmail'),
                        sortable: true,
                        render: (row) => row.email,
                    },
                    {
                        key: 'role',
                        priority: 'status',
                        label: t('admin.users.columnRole'),
                        render: (row) => (
                            <Badge tone={row.role ? 'primary' : 'outline'}>
                                {t(`admin.users.role.${row.role ?? 'none'}`)}
                            </Badge>
                        ),
                    },
                    {
                        key: 'status',
                        priority: 'status',
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
                        priority: 'optional',
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
                            id: 'edit',
                            label: t('admin.users.actionEdit'),
                            icon: Pencil,
                            onSelect: () => router.visit(usersEdit.url(row.id)),
                        },
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
                        ...(row.isSelf
                            ? []
                            : [
                                  {
                                      id: 'delete',
                                      label: t('admin.users.actionDelete'),
                                      icon: Trash2,
                                      tone: 'destructive' as const,
                                      onSelect: () => setUserToDelete(row),
                                  },
                              ]),
                    ],
                }}
            />

            <ConfirmDialog
                open={userToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUserToDelete(null);
                    }
                }}
                title={t('admin.users.deleteTitle')}
                description={t('admin.users.deleteDescription')}
                confirmLabel={t('admin.users.deleteConfirm')}
                cancelLabel={t('admin.users.cancel')}
                closeLabel={t('actions.close')}
                tone="destructive"
                isPending={isDeleting}
                onConfirm={confirmDelete}
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
