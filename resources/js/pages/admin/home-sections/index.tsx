import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Alert,
    Badge,
    DataTable,
    EmptyState,
    FormSection,
    Link,
    OrderableList,
    PageHeader,
    SelectField,
    Stack,
    SwitchField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { edit, index, reorder, visibility } from '@/routes/admin/home-sections';

type IndexProps = App.Data.Admin.HomeSections.HomeSectionIndexData;
type Row = App.Data.Admin.HomeSections.HomeSectionListItemData;

/**
 * Home page sections of one content locale: order (move up/down), show or
 * hide, and a link to the content form of each section.
 */
export default function AdminHomeSectionsIndex() {
    const page = usePage<IndexProps & { errors?: Record<string, string> }>();
    const { items, locale, locales, can } = page.props;
    const { t, formatDate } = useTranslation();
    const [pendingOrder, setPendingOrder] = useState<number[] | null>(null);
    const [togglingId, setTogglingId] = useState<number | null>(null);
    const conflict = page.props.errors?.conflict;

    const rows: Row[] = pendingOrder
        ? pendingOrder.flatMap((id) => items.filter((item) => item.id === id))
        : items;

    const typeLabel = (row: Row) => t(`admin.homeSections.types.${row.type}`);

    function changeOrder(ids: Array<string | number>) {
        const numericIds = ids.map(Number);
        setPendingOrder(numericIds);
        router.put(
            reorder.url(),
            { locale, ids: numericIds },
            {
                preserveScroll: true,
                onFinish: () => setPendingOrder(null),
            },
        );
    }

    function toggle(row: Row, enabled: boolean) {
        setTogglingId(row.id);
        router.patch(
            visibility.url(row.id),
            { enabled },
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
            },
        );
    }

    return (
        <>
            <Head title={t('admin.homeSections.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.homeSections.title')}
                    description={t('admin.homeSections.description')}
                    actions={
                        <SelectField
                            name="locale"
                            label={t('admin.homeSections.localeLabel')}
                            value={locale}
                            options={locales.available.map((option) => ({
                                value: option.code,
                                label: option.native,
                            }))}
                            onChange={(value) =>
                                router.get(
                                    index.url({ query: { locale: value } }),
                                )
                            }
                        />
                    }
                />

                {conflict && <Alert tone="danger" title={conflict} />}

                {rows.length === 0 ? (
                    <EmptyState
                        title={t('admin.homeSections.emptyTitle')}
                        description={t('admin.homeSections.emptyDescription')}
                    />
                ) : (
                    <>
                        <FormSection
                            title={t('admin.homeSections.orderTitle')}
                            description={t(
                                'admin.homeSections.orderDescription',
                            )}
                        >
                            <OrderableList
                                label={t('admin.homeSections.orderLabel')}
                                disabled={!can.reorder || pendingOrder !== null}
                                items={rows.map((row) => ({
                                    id: row.id,
                                    label: typeLabel(row),
                                    meta: row.enabled
                                        ? t('admin.homeSections.enabled')
                                        : t('admin.homeSections.disabled'),
                                }))}
                                onReorder={changeOrder}
                            />
                        </FormSection>

                        <FormSection
                            title={t('admin.homeSections.sectionsTitle')}
                        >
                            <DataTable<Row>
                                caption={t('admin.homeSections.tableCaption')}
                                rows={rows}
                                rowKey={(row) => row.id}
                                emptyState={null}
                                columns={[
                                    {
                                        key: 'section',
                                        header: t(
                                            'admin.homeSections.columnSection',
                                        ),
                                        render: (row) =>
                                            can.update ? (
                                                <Link
                                                    href={edit(row.id)}
                                                    tone="primary"
                                                    ariaLabel={t(
                                                        'admin.homeSections.editLabel',
                                                        {
                                                            section:
                                                                typeLabel(row),
                                                        },
                                                    )}
                                                >
                                                    {typeLabel(row)}
                                                </Link>
                                            ) : (
                                                typeLabel(row)
                                            ),
                                    },
                                    {
                                        key: 'title',
                                        header: t(
                                            'admin.homeSections.columnTitle',
                                        ),
                                        render: (row) =>
                                            row.title ??
                                            t('admin.homeSections.noTitle'),
                                    },
                                    {
                                        key: 'visible',
                                        header: t(
                                            'admin.homeSections.columnVisible',
                                        ),
                                        render: (row) =>
                                            can.update ? (
                                                <SwitchField
                                                    name={`enabled-${row.id}`}
                                                    label={t(
                                                        'admin.homeSections.visibleLabel',
                                                        {
                                                            section:
                                                                typeLabel(row),
                                                        },
                                                    )}
                                                    checked={row.enabled}
                                                    disabled={
                                                        togglingId !== null
                                                    }
                                                    onChange={(enabled) =>
                                                        toggle(row, enabled)
                                                    }
                                                />
                                            ) : (
                                                <Badge
                                                    tone={
                                                        row.enabled
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {row.enabled
                                                        ? t(
                                                              'admin.homeSections.enabled',
                                                          )
                                                        : t(
                                                              'admin.homeSections.disabled',
                                                          )}
                                                </Badge>
                                            ),
                                    },
                                    {
                                        key: 'updatedAt',
                                        header: t(
                                            'admin.homeSections.columnUpdatedAt',
                                        ),
                                        render: (row) =>
                                            row.updatedAt
                                                ? formatDate(row.updatedAt)
                                                : '—',
                                    },
                                ]}
                            />
                        </FormSection>
                    </>
                )}
            </Stack>
        </>
    );
}

AdminHomeSectionsIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.homeSections.title', href: index() },
    ],
};
