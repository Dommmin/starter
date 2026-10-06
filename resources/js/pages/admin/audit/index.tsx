import { Head, usePage } from '@inertiajs/react';
import { ResourceTable, Text } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { tableSortLabels } from '@/lib/table-sort-labels';
import { index as adminIndex } from '@/routes/admin';
import { index as auditIndex } from '@/routes/admin/audit';

type AuditRow = App.Data.Admin.Audit.AuditLogListItemData;

export default function AdminAuditIndex() {
    const { items, pagination, filters, actions } =
        usePage<App.Data.Admin.Audit.AuditLogIndexData>().props;
    const { t, formatDate } = useTranslation();

    const actionLabel = (action: App.Enums.AuditAction) =>
        t(`admin.audit.actions.${action}`);

    const subjectLabel = (row: AuditRow) => {
        const type = t(`admin.audit.subjects.${row.subjectType}`, {
            defaultValue: row.subjectType,
        });

        return row.subjectId === null
            ? type
            : t('admin.audit.subjectLabel', { type, id: row.subjectId });
    };

    return (
        <>
            <Head title={t('admin.audit.title')} />

            <ResourceTable<AuditRow>
                url={auditIndex()}
                header={{
                    title: t('admin.audit.title'),
                    description: t('admin.audit.description'),
                }}
                rows={items}
                rowKey={(row) => row.id}
                pagination={pagination}
                filters={filters}
                labels={{
                    caption: t('admin.audit.tableCaption'),
                    sort: tableSortLabels(t),
                    clearFilters: t('admin.audit.clearFilters'),
                    emptyTitle: t('admin.audit.emptyTitle'),
                    emptyDescription: t('admin.audit.emptyDescription'),
                    noResultsTitle: t('admin.audit.emptyFilteredTitle'),
                    noResultsDescription: t(
                        'admin.audit.emptyFilteredDescription',
                    ),
                    errorTitle: t('admin.audit.errorTitle'),
                    errorRetry: t('admin.audit.errorRetry'),
                    previousPage: t('admin.audit.previousPage'),
                    nextPage: t('admin.audit.nextPage'),
                    paginationSummary: (range) =>
                        t('admin.audit.paginationSummary', range),
                }}
                filterFields={[
                    {
                        name: 'action',
                        label: t('admin.audit.filterActionLabel'),
                        defaultValue: 'all',
                        options: [
                            { value: 'all', label: t('admin.audit.filterAll') },
                            ...actions.map((action) => ({
                                value: action,
                                label: actionLabel(action),
                            })),
                        ],
                    },
                ]}
                columns={[
                    {
                        key: 'created_at',
                        priority: 'secondary',
                        label: t('admin.audit.columnDate'),
                        sortable: true,
                        render: (row) =>
                            row.createdAt
                                ? formatDate(row.createdAt, {
                                      dateStyle: 'medium',
                                      timeStyle: 'short',
                                  })
                                : '—',
                    },
                    {
                        key: 'actor',
                        priority: 'secondary',
                        label: t('admin.audit.columnActor'),
                        render: (row) =>
                            row.actorName ?? t('admin.audit.system'),
                    },
                    {
                        key: 'action',
                        priority: 'primary',
                        label: t('admin.audit.columnAction'),
                        render: (row) => actionLabel(row.action),
                    },
                    {
                        key: 'subject',
                        priority: 'secondary',
                        label: t('admin.audit.columnSubject'),
                        render: subjectLabel,
                    },
                    {
                        key: 'changes',
                        priority: 'optional',
                        label: t('admin.audit.columnChanges'),
                        render: (row) =>
                            row.changedFields.length > 0 ? (
                                <Text variant="code" as="span">
                                    {row.changedFields.join(', ')}
                                </Text>
                            ) : (
                                t('admin.audit.noChanges')
                            ),
                    },
                ]}
            />
        </>
    );
}

AdminAuditIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.audit.title',
            href: auditIndex(),
        },
    ],
};
