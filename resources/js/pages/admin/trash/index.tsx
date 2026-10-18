import { Head, router, usePage } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    ActionMenu,
    Badge,
    ConfirmDialog,
    DataTable,
    EmptyState,
    PageHeader,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { destroy, index, restore } from '@/routes/admin/trash';

type IndexProps = App.Data.Admin.Trash.TrashIndexData;
type Row = App.Data.Admin.Trash.TrashItemData;

/**
 * Trash of pages and articles (administrators only): restore a record or
 * delete it for good before the daily cleanup does after the retention.
 */
export default function AdminTrashIndex() {
    const { items, retentionDays } = usePage<IndexProps>().props;
    const { t, formatDate } = useTranslation();
    const [purging, setPurging] = useState<Row | null>(null);
    const [isPending, setIsPending] = useState(false);

    const titleOf = (row: Row) =>
        row.title ?? t('admin.trash.untitled', { id: row.id });
    const date = (value: string | null) =>
        value ? formatDate(value, { dateStyle: 'medium' }) : '—';

    function restoreRow(row: Row) {
        router.put(restore.url({ type: row.type, id: row.id }));
    }

    function confirmPurge() {
        if (purging === null) {
            return;
        }

        setIsPending(true);
        router.delete(destroy.url({ type: purging.type, id: purging.id }), {
            onFinish: () => {
                setIsPending(false);
                setPurging(null);
            },
        });
    }

    return (
        <>
            <Head title={t('admin.trash.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.trash.title')}
                    description={t('admin.trash.description', {
                        days: retentionDays,
                    })}
                />

                <DataTable<Row>
                    caption={t('admin.trash.tableCaption')}
                    rows={items}
                    rowKey={(row) => `${row.type}-${row.id}`}
                    emptyState={
                        <EmptyState
                            title={t('admin.trash.emptyTitle')}
                            description={t('admin.trash.emptyDescription')}
                        />
                    }
                    columns={[
                        {
                            key: 'title',
                            priority: 'primary',
                            header: t('admin.trash.columnTitle'),
                            render: (row) => titleOf(row),
                        },
                        {
                            key: 'type',
                            priority: 'status',
                            header: t('admin.trash.columnType'),
                            render: (row) => (
                                <Badge tone="neutral">
                                    {t(`admin.trash.types.${row.type}`)}
                                </Badge>
                            ),
                        },
                        {
                            key: 'deleted',
                            priority: 'secondary',
                            header: t('admin.trash.columnDeleted'),
                            render: (row) => date(row.deletedAt),
                        },
                        {
                            key: 'purge',
                            priority: 'card',
                            header: t('admin.trash.columnPurge'),
                            render: (row) => date(row.purgeAt),
                        },
                        {
                            key: 'actions',
                            priority: 'actions',
                            header: t('admin.trash.columnActions'),
                            align: 'end',
                            render: (row) => (
                                <ActionMenu
                                    triggerLabel={t('admin.trash.rowActions', {
                                        title: titleOf(row),
                                    })}
                                    items={[
                                        {
                                            id: 'restore',
                                            label: t('admin.trash.restore'),
                                            icon: RotateCcw,
                                            onSelect: () => restoreRow(row),
                                        },
                                        {
                                            id: 'purge',
                                            label: t('admin.trash.purge'),
                                            icon: Trash2,
                                            tone: 'destructive',
                                            onSelect: () => setPurging(row),
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            </Stack>

            <ConfirmDialog
                open={purging !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPurging(null);
                    }
                }}
                title={t('admin.trash.purgeTitle')}
                description={t('admin.trash.purgeDescription', {
                    title: purging ? titleOf(purging) : '',
                })}
                confirmLabel={t('admin.trash.purgeConfirm')}
                cancelLabel={t('admin.trash.cancel')}
                closeLabel={t('actions.close')}
                tone="destructive"
                isPending={isPending}
                onConfirm={confirmPurge}
            />
        </>
    );
}

AdminTrashIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.trash.title', href: index() },
    ],
};
