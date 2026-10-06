import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    Button,
    ConfirmDialog,
    Link,
    ResourceTable,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { tableSortLabels } from '@/lib/table-sort-labels';
import { index as adminIndex } from '@/routes/admin';
import {
    create as pagesCreate,
    destroy as pagesDestroy,
    edit as pagesEdit,
    index as pagesIndex,
} from '@/routes/admin/pages';

type PageRow = App.Data.Admin.Pages.PageListItemData;

export default function AdminPagesIndex() {
    const { items, pagination, filters, locales, can } =
        usePage<App.Data.Admin.Pages.PageIndexData>().props;
    const { t, formatDate, locale: adminLocale } = useTranslation();
    /** Mirrors `ListPagesRequest::defaultContentLocale()`. */
    const defaultContentLocale = locales.available.some(
        (locale) => locale.code === adminLocale,
    )
        ? adminLocale
        : locales.default;
    const [pageToDelete, setPageToDelete] = useState<PageRow | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const localeName = (code: string) =>
        locales.available.find((locale) => locale.code === code)?.native ??
        code;

    const rowTitle = (row: PageRow) => row.title || t('admin.pages.untitled');

    function confirmDelete() {
        if (!pageToDelete) {
            return;
        }

        setIsDeleting(true);
        router.delete(pagesDestroy.url(pageToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setPageToDelete(null),
            onFinish: () => setIsDeleting(false),
        });
    }

    return (
        <>
            <Head title={t('admin.pages.title')} />

            <ResourceTable<PageRow>
                url={pagesIndex()}
                header={{
                    title: t('admin.pages.title'),
                    description: t('admin.pages.description'),
                    actions: can.create ? (
                        <Button href={pagesCreate()}>
                            {t('admin.pages.create')}
                        </Button>
                    ) : undefined,
                }}
                emptyAction={
                    can.create ? (
                        <Button href={pagesCreate()}>
                            {t('admin.pages.create')}
                        </Button>
                    ) : undefined
                }
                rows={items}
                rowKey={(row) => row.id}
                pagination={pagination}
                filters={filters}
                searchable
                labels={{
                    caption: t('admin.pages.tableCaption'),
                    sort: tableSortLabels(t),
                    searchLabel: t('admin.pages.searchLabel'),
                    searchPlaceholder: t('admin.pages.searchPlaceholder'),
                    searchClear: t('admin.pages.searchClear'),
                    clearFilters: t('admin.pages.clearFilters'),
                    emptyTitle: t('admin.pages.emptyTitle'),
                    emptyDescription: t('admin.pages.emptyDescription'),
                    noResultsTitle: t('admin.pages.emptyFilteredTitle'),
                    noResultsDescription: t(
                        'admin.pages.emptyFilteredDescription',
                    ),
                    errorTitle: t('admin.pages.errorTitle'),
                    errorRetry: t('admin.pages.errorRetry'),
                    previousPage: t('admin.pages.previousPage'),
                    nextPage: t('admin.pages.nextPage'),
                    paginationSummary: (range) =>
                        t('admin.pages.paginationSummary', range),
                }}
                filterFields={[
                    {
                        name: 'status',
                        label: t('admin.pages.filterStatusLabel'),
                        defaultValue: 'all',
                        options: [
                            { value: 'all', label: t('admin.pages.filterAll') },
                            {
                                value: 'draft',
                                label: t('admin.pages.status.draft'),
                            },
                            {
                                value: 'published',
                                label: t('admin.pages.status.published'),
                            },
                        ],
                    },
                    {
                        name: 'locale',
                        label: t('admin.pages.filterLocaleLabel'),
                        defaultValue: defaultContentLocale,
                        options: locales.available.map((locale) => ({
                            value: locale.code,
                            label: locale.native,
                        })),
                    },
                ]}
                columns={[
                    {
                        key: 'title',
                        priority: 'primary',
                        label: t('admin.pages.columnTitle'),
                        sortable: true,
                        render: (row) => (
                            <Stack gap="none">
                                <Link href={pagesEdit(row.id)} tone="primary">
                                    {rowTitle(row)}
                                </Link>
                                {row.locale !== filters.locale && (
                                    <Text variant="caption" tone="muted">
                                        {t('admin.pages.missingTranslation')}
                                    </Text>
                                )}
                            </Stack>
                        ),
                    },
                    {
                        key: 'slug',
                        priority: 'secondary',
                        label: t('admin.pages.columnSlug'),
                        render: (row) => (
                            <Text variant="code" as="span">
                                {row.slug}
                            </Text>
                        ),
                    },
                    {
                        key: 'status',
                        priority: 'status',
                        label: t('admin.pages.columnStatus'),
                        render: (row) => (
                            <Badge
                                tone={
                                    row.status === 'published'
                                        ? 'success'
                                        : 'neutral'
                                }
                            >
                                {t(`admin.pages.status.${row.status}`)}
                            </Badge>
                        ),
                    },
                    {
                        key: 'locales',
                        priority: 'card',
                        label: t('admin.pages.columnLocales'),
                        render: (row) =>
                            row.locales.map(localeName).join(', ') || '—',
                    },
                    {
                        key: 'updated_at',
                        priority: 'optional',
                        label: t('admin.pages.columnUpdatedAt'),
                        sortable: true,
                        render: (row) =>
                            row.updatedAt ? formatDate(row.updatedAt) : '—',
                    },
                ]}
                rowActions={{
                    label: (row) =>
                        t('admin.pages.rowActionsLabel', {
                            title: rowTitle(row),
                        }),
                    items: (row) => [
                        {
                            id: 'edit',
                            label: t('admin.pages.actionEdit'),
                            icon: Pencil,
                            onSelect: () => router.visit(pagesEdit.url(row.id)),
                        },
                        ...(can.delete
                            ? [
                                  {
                                      id: 'delete',
                                      label: t('admin.pages.actionDelete'),
                                      icon: Trash2,
                                      tone: 'destructive' as const,
                                      onSelect: () => setPageToDelete(row),
                                  },
                              ]
                            : []),
                    ],
                }}
            />

            <ConfirmDialog
                open={pageToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPageToDelete(null);
                    }
                }}
                title={t('admin.pages.deleteTitle')}
                description={t('admin.pages.deleteDescription')}
                confirmLabel={t('admin.pages.deleteConfirm')}
                cancelLabel={t('admin.pages.cancel')}
                closeLabel={t('actions.close')}
                tone="destructive"
                isPending={isDeleting}
                onConfirm={confirmDelete}
            />
        </>
    );
}

AdminPagesIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.pages.title',
            href: pagesIndex(),
        },
    ],
};
