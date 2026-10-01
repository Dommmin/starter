import { Head, router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { Link, ResourceTable } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { tableSortLabels } from '@/lib/table-sort-labels';
import { index as adminIndex } from '@/routes/admin';
import { index, show } from '@/routes/admin/contact';
import { ContactStatusBadge } from './status-badge';

type Row = App.Data.Admin.Contact.ContactMessageListItemData;
type IndexProps = App.Data.Admin.Contact.ContactMessageIndexData;

/**
 * Admin list of contact form messages with their delivery status.
 */
export default function AdminContactIndex() {
    const { items, pagination, filters } = usePage<IndexProps>().props;
    const { t, formatDate } = useTranslation();

    return (
        <>
            <Head title={t('admin.contact.title')} />

            <ResourceTable<Row>
                url={index()}
                header={{
                    title: t('admin.contact.title'),
                    description: t('admin.contact.description'),
                }}
                rows={items}
                rowKey={(row) => row.id}
                pagination={pagination}
                filters={filters}
                searchable
                labels={{
                    caption: t('admin.contact.tableCaption'),
                    sort: tableSortLabels(t),
                    searchLabel: t('admin.contact.searchLabel'),
                    searchPlaceholder: t('admin.contact.searchPlaceholder'),
                    searchClear: t('admin.contact.searchClear'),
                    clearFilters: t('admin.contact.clearFilters'),
                    emptyTitle: t('admin.contact.emptyTitle'),
                    emptyDescription: t('admin.contact.emptyDescription'),
                    noResultsTitle: t('admin.contact.emptyFilteredTitle'),
                    noResultsDescription: t(
                        'admin.contact.emptyFilteredDescription',
                    ),
                    errorTitle: t('admin.contact.errorTitle'),
                    errorRetry: t('admin.contact.errorRetry'),
                    previousPage: t('admin.contact.previousPage'),
                    nextPage: t('admin.contact.nextPage'),
                    paginationSummary: (range) =>
                        t('admin.contact.paginationSummary', range),
                }}
                filterFields={[
                    {
                        name: 'status',
                        label: t('admin.contact.fields.status'),
                        defaultValue: 'all',
                        options: [
                            {
                                value: 'all',
                                label: t('admin.contact.filterAll'),
                            },
                            {
                                value: 'pending',
                                label: t('admin.contact.statuses.pending'),
                            },
                            {
                                value: 'sent',
                                label: t('admin.contact.statuses.sent'),
                            },
                            {
                                value: 'failed',
                                label: t('admin.contact.statuses.failed'),
                            },
                        ],
                    },
                ]}
                columns={[
                    {
                        key: 'name',
                        priority: 'primary',
                        label: t('admin.contact.fields.name'),
                        render: (row) => (
                            <Link href={show(row.id)} tone="primary">
                                {row.name}
                            </Link>
                        ),
                    },
                    {
                        key: 'email',
                        priority: 'card',
                        label: t('admin.contact.fields.email'),
                        render: (row) => row.email,
                    },
                    {
                        key: 'status',
                        priority: 'status',
                        label: t('admin.contact.fields.status'),
                        render: (row) => (
                            <ContactStatusBadge status={row.status} />
                        ),
                    },
                    {
                        key: 'created_at',
                        priority: 'secondary',
                        label: t('admin.contact.fields.createdAt'),
                        sortable: true,
                        render: (row) =>
                            row.createdAt ? formatDate(row.createdAt) : '—',
                    },
                ]}
                rowActions={{
                    label: (row) =>
                        t('admin.contact.rowActionsLabel', { name: row.name }),
                    items: (row) => [
                        {
                            id: 'show',
                            label: t('admin.contact.actionShow'),
                            icon: Eye,
                            onSelect: () => router.visit(show.url(row.id)),
                        },
                    ],
                }}
            />
        </>
    );
}

AdminContactIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.contact.title', href: index() },
    ],
};
