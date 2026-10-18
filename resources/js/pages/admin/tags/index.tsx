import { Head, usePage } from '@inertiajs/react';
import {
    Button,
    DataTable,
    EmptyState,
    Inline,
    Link,
    PageHeader,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { show as importShow } from '@/routes/admin/import';
import { create, edit, index } from '@/routes/admin/tags';

type IndexProps = App.Data.Admin.Tags.TagIndexData;
type Row = App.Data.Admin.Tags.TagListItemData;

/**
 * Article tags: the name shown to visitors per language and the shared
 * slug of the public filter.
 */
export default function AdminTagsIndex() {
    const { items, canCreate, canImport } = usePage<IndexProps>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.tags.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.tags.title')}
                    description={t('admin.tags.description')}
                    actions={
                        canCreate || canImport ? (
                            <Inline gap="tight">
                                {canImport && (
                                    <Button
                                        variant="outline"
                                        href={importShow('tags')}
                                    >
                                        {t('admin.import.open')}
                                    </Button>
                                )}
                                {canCreate && (
                                    <Button href={create()}>
                                        {t('admin.tags.create')}
                                    </Button>
                                )}
                            </Inline>
                        ) : undefined
                    }
                />

                <DataTable<Row>
                    caption={t('admin.tags.tableCaption')}
                    rows={items}
                    rowKey={(row) => row.id}
                    emptyState={
                        <EmptyState
                            title={t('admin.tags.emptyTitle')}
                            description={t('admin.tags.emptyDescription')}
                        />
                    }
                    columns={[
                        {
                            key: 'name',
                            priority: 'primary',
                            header: t('admin.tags.columnName'),
                            render: (row) => (
                                <Link href={edit(row.id)} tone="default">
                                    {row.name}
                                </Link>
                            ),
                        },
                        {
                            key: 'slug',
                            priority: 'secondary',
                            header: t('admin.tags.fields.slug'),
                            render: (row) => row.slug,
                        },
                        {
                            key: 'articles',
                            priority: 'card',
                            header: t('admin.tags.columnArticles'),
                            render: (row) => row.articlesCount,
                        },
                    ]}
                />
            </Stack>
        </>
    );
}

AdminTagsIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.tags.title', href: index() },
    ],
};
