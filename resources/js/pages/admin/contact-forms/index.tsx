import { Head, usePage } from '@inertiajs/react';
import {
    Badge,
    Button,
    DataTable,
    EmptyState,
    Link,
    PageHeader,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, edit, index } from '@/routes/admin/contact-forms';

type IndexProps = App.Data.Admin.ContactForms.ContactFormIndexData;
type Row = App.Data.Admin.ContactForms.ContactFormListItemData;

/**
 * Contact forms of the public site (administrators only): the default form
 * and e.g. a quote form, each with its extra fields.
 */
export default function AdminContactFormsIndex() {
    const { items, canCreate } = usePage<IndexProps>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.contactForms.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.contactForms.title')}
                    description={t('admin.contactForms.description')}
                    actions={
                        canCreate ? (
                            <Button href={create()}>
                                {t('admin.contactForms.create')}
                            </Button>
                        ) : undefined
                    }
                />

                <DataTable<Row>
                    caption={t('admin.contactForms.tableCaption')}
                    rows={items}
                    rowKey={(row) => row.id}
                    emptyState={
                        <EmptyState
                            title={t('admin.contactForms.emptyTitle')}
                            description={t(
                                'admin.contactForms.emptyDescription',
                            )}
                        />
                    }
                    columns={[
                        {
                            key: 'title',
                            priority: 'primary',
                            header: t('admin.contactForms.columnTitle'),
                            render: (row) => (
                                <Link href={edit(row.id)} tone="default">
                                    {row.title}
                                </Link>
                            ),
                        },
                        {
                            key: 'key',
                            priority: 'secondary',
                            header: t('admin.contactForms.fields.key'),
                            render: (row) => row.key,
                        },
                        {
                            key: 'fields',
                            priority: 'card',
                            header: t('admin.contactForms.columnFields'),
                            render: (row) => row.fieldsCount,
                        },
                        {
                            key: 'enabled',
                            priority: 'status',
                            header: t('admin.contactForms.columnStatus'),
                            render: (row) => (
                                <Badge
                                    tone={row.enabled ? 'success' : 'neutral'}
                                >
                                    {row.enabled
                                        ? t('admin.contactForms.enabled')
                                        : t('admin.contactForms.disabled')}
                                </Badge>
                            ),
                        },
                    ]}
                />
            </Stack>
        </>
    );
}

AdminContactFormsIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.contactForms.title', href: index() },
    ],
};
