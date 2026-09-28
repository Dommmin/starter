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
import { index as adminIndex } from '@/routes/admin';
import {
    create as articlesCreate,
    destroy as articlesDestroy,
    edit as articlesEdit,
    index as articlesIndex,
} from '@/routes/admin/articles';

type ArticleRow = App.Data.Admin.Articles.ArticleListItemData;

export default function AdminArticlesIndex() {
    const { items, pagination, filters, locales, can } =
        usePage<App.Data.Admin.Articles.ArticleIndexData>().props;
    const { t, formatDate, locale: adminLocale } = useTranslation();
    /** Mirrors `ListArticlesRequest::defaultContentLocale()`. */
    const defaultContentLocale = locales.available.some(
        (locale) => locale.code === adminLocale,
    )
        ? adminLocale
        : locales.default;
    const [articleToDelete, setArticleToDelete] = useState<ArticleRow | null>(
        null,
    );
    const [isDeleting, setIsDeleting] = useState(false);

    const localeName = (code: string) =>
        locales.available.find((locale) => locale.code === code)?.native ??
        code;

    const rowTitle = (row: ArticleRow) =>
        row.title || t('admin.articles.untitled');

    function confirmDelete() {
        if (!articleToDelete) {
            return;
        }

        setIsDeleting(true);
        router.delete(articlesDestroy.url(articleToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setArticleToDelete(null),
            onFinish: () => setIsDeleting(false),
        });
    }

    return (
        <>
            <Head title={t('admin.articles.title')} />

            <ResourceTable<ArticleRow>
                url={articlesIndex()}
                header={{
                    title: t('admin.articles.title'),
                    description: t('admin.articles.description'),
                    actions: can.create ? (
                        <Button href={articlesCreate()}>
                            {t('admin.articles.create')}
                        </Button>
                    ) : undefined,
                }}
                rows={items}
                rowKey={(row) => row.id}
                pagination={pagination}
                filters={filters}
                searchable
                labels={{
                    caption: t('admin.articles.tableCaption'),
                    searchLabel: t('admin.articles.searchLabel'),
                    searchPlaceholder: t('admin.articles.searchPlaceholder'),
                    searchClear: t('admin.articles.searchClear'),
                    clearFilters: t('admin.articles.clearFilters'),
                    emptyTitle: t('admin.articles.emptyTitle'),
                    emptyDescription: t('admin.articles.emptyDescription'),
                    noResultsTitle: t('admin.articles.emptyFilteredTitle'),
                    noResultsDescription: t(
                        'admin.articles.emptyFilteredDescription',
                    ),
                    errorTitle: t('admin.articles.errorTitle'),
                    errorRetry: t('admin.articles.errorRetry'),
                    previousPage: t('admin.articles.previousPage'),
                    nextPage: t('admin.articles.nextPage'),
                    paginationSummary: (range) =>
                        t('admin.articles.paginationSummary', range),
                }}
                filterFields={[
                    {
                        name: 'status',
                        label: t('admin.articles.filterStatusLabel'),
                        defaultValue: 'all',
                        options: [
                            {
                                value: 'all',
                                label: t('admin.articles.filterAll'),
                            },
                            {
                                value: 'draft',
                                label: t('admin.articles.status.draft'),
                            },
                            {
                                value: 'published',
                                label: t('admin.articles.status.published'),
                            },
                        ],
                    },
                    {
                        name: 'locale',
                        label: t('admin.articles.filterLocaleLabel'),
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
                        label: t('admin.articles.columnTitle'),
                        sortable: true,
                        render: (row) => (
                            <Stack gap="none">
                                <Link
                                    href={articlesEdit(row.id)}
                                    tone="primary"
                                >
                                    {rowTitle(row)}
                                </Link>
                                {row.locale !== filters.locale && (
                                    <Text variant="caption" tone="muted">
                                        {t('admin.articles.missingTranslation')}
                                    </Text>
                                )}
                            </Stack>
                        ),
                    },
                    {
                        key: 'status',
                        label: t('admin.articles.columnStatus'),
                        render: (row) => (
                            <Badge
                                tone={
                                    row.scheduled
                                        ? 'primary'
                                        : row.status === 'published'
                                          ? 'success'
                                          : 'neutral'
                                }
                            >
                                {row.scheduled
                                    ? t('admin.articles.status.scheduled')
                                    : t(`admin.articles.status.${row.status}`)}
                            </Badge>
                        ),
                    },
                    {
                        key: 'published_at',
                        label: t('admin.articles.columnPublishedAt'),
                        render: (row) =>
                            row.publishedAt ? formatDate(row.publishedAt) : '—',
                    },
                    {
                        key: 'locales',
                        label: t('admin.articles.columnLocales'),
                        render: (row) =>
                            row.locales.map(localeName).join(', ') || '—',
                    },
                    {
                        key: 'updated_at',
                        label: t('admin.articles.columnUpdatedAt'),
                        sortable: true,
                        render: (row) =>
                            row.updatedAt ? formatDate(row.updatedAt) : '—',
                    },
                ]}
                rowActions={{
                    label: (row) =>
                        t('admin.articles.rowActionsLabel', {
                            title: rowTitle(row),
                        }),
                    items: (row) => [
                        {
                            id: 'edit',
                            label: t('admin.articles.actionEdit'),
                            icon: Pencil,
                            onSelect: () =>
                                router.visit(articlesEdit.url(row.id)),
                        },
                        ...(can.delete
                            ? [
                                  {
                                      id: 'delete',
                                      label: t('admin.articles.actionDelete'),
                                      icon: Trash2,
                                      tone: 'destructive' as const,
                                      onSelect: () => setArticleToDelete(row),
                                  },
                              ]
                            : []),
                    ],
                }}
            />

            <ConfirmDialog
                open={articleToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setArticleToDelete(null);
                    }
                }}
                title={t('admin.articles.deleteTitle')}
                description={t('admin.articles.deleteDescription')}
                confirmLabel={t('admin.articles.deleteConfirm')}
                cancelLabel={t('admin.articles.cancel')}
                closeLabel={t('actions.close')}
                tone="destructive"
                isPending={isDeleting}
                onConfirm={confirmDelete}
            />
        </>
    );
}

AdminArticlesIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.articles.title',
            href: articlesIndex(),
        },
    ],
};
