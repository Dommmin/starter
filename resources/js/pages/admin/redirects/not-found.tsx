import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight, Trash2 } from 'lucide-react';
import {
    ActionMenu,
    Badge,
    Button,
    DataTable,
    EmptyState,
    PageHeader,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, index } from '@/routes/admin/redirects';
import {
    destroy as dismissNotFound,
    index as notFoundIndex,
} from '@/routes/admin/redirects/not-found';

type IndexProps = App.Data.Admin.Redirects.NotFoundLogIndexData;
type Row = App.Data.Admin.Redirects.NotFoundLogItemData;

export default function AdminNotFoundLogsIndex() {
    const { items, canCreateRedirect } = usePage<IndexProps>().props;
    const { t, formatDate } = useTranslation();

    function dismiss(row: Row) {
        router.delete(dismissNotFound.url(row.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={t('admin.redirects.notFound.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.redirects.notFound.title')}
                    description={t('admin.redirects.notFound.description')}
                    actions={
                        <Button variant="outline" href={index()}>
                            {t('admin.redirects.notFound.backToRedirects')}
                        </Button>
                    }
                />

                <DataTable<Row>
                    caption={t('admin.redirects.notFound.tableCaption')}
                    rows={items}
                    rowKey={(row) => row.id}
                    emptyState={
                        <EmptyState
                            title={t('admin.redirects.notFound.emptyTitle')}
                            description={t(
                                'admin.redirects.notFound.emptyDescription',
                            )}
                        />
                    }
                    columns={[
                        {
                            key: 'path',
                            priority: 'primary',
                            header: t('admin.redirects.notFound.columnPath'),
                            render: (row) => row.path,
                        },
                        {
                            key: 'hits',
                            priority: 'status',
                            header: t('admin.redirects.notFound.columnHits'),
                            render: (row) => (
                                <Badge tone="neutral">{row.hits}</Badge>
                            ),
                        },
                        {
                            key: 'lastReferer',
                            priority: 'secondary',
                            header: t('admin.redirects.notFound.columnReferer'),
                            render: (row) => row.lastReferer ?? '—',
                        },
                        {
                            key: 'lastSeenAt',
                            priority: 'optional',
                            header: t(
                                'admin.redirects.notFound.columnLastSeen',
                            ),
                            render: (row) =>
                                row.lastSeenAt
                                    ? formatDate(row.lastSeenAt)
                                    : '—',
                        },
                        {
                            key: 'actions',
                            priority: 'actions',
                            header: t('admin.redirects.notFound.columnActions'),
                            align: 'end',
                            render: (row) => (
                                <ActionMenu
                                    triggerLabel={t(
                                        'admin.redirects.rowActionsLabel',
                                        { title: row.path },
                                    )}
                                    items={[
                                        ...(canCreateRedirect
                                            ? [
                                                  {
                                                      id: 'create-redirect',
                                                      label: t(
                                                          'admin.redirects.notFound.createRedirect',
                                                      ),
                                                      icon: ArrowRight,
                                                      onSelect: () =>
                                                          router.visit(
                                                              create.url({
                                                                  query: {
                                                                      source_path:
                                                                          row.path,
                                                                  },
                                                              }),
                                                          ),
                                                  },
                                              ]
                                            : []),
                                        {
                                            id: 'dismiss',
                                            label: t(
                                                'admin.redirects.notFound.dismiss',
                                            ),
                                            icon: Trash2,
                                            tone: 'destructive' as const,
                                            onSelect: () => dismiss(row),
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            </Stack>
        </>
    );
}

AdminNotFoundLogsIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.redirects.title', href: index() },
        { title: 'admin.redirects.notFound.title', href: notFoundIndex() },
    ],
};
