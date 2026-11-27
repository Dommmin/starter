import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Alert,
    EmptyState,
    FormSection,
    OrderableList,
    PageHeader,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index, order, reorder } from '@/routes/admin/faqs';

type OrderProps = App.Data.Admin.Faqs.FaqOrderData;
type Item = App.Data.Admin.Faqs.FaqOrderItemData;

/**
 * Shared order of every faq (one sequence for all languages; the public
 * list of each language shows its own and the all-languages questions in
 * this order). Moving up/down stores the complete list at once.
 */
export default function AdminFaqsOrder() {
    const page = usePage<OrderProps & { errors?: Record<string, string> }>();
    const { items, canReorder, locales } = page.props;
    const { t } = useTranslation();
    const [pendingOrder, setPendingOrder] = useState<number[] | null>(null);
    const conflict = page.props.errors?.conflict;

    const rows: Item[] = pendingOrder
        ? pendingOrder.flatMap((id) => items.filter((item) => item.id === id))
        : items;

    const localeLabel = (item: Item) =>
        item.locale === null
            ? t('admin.faqs.fields.localeAll')
            : (locales.available.find((locale) => locale.code === item.locale)
                  ?.native ?? item.locale.toUpperCase());

    function changeOrder(ids: Array<string | number>) {
        const numericIds = ids.map(Number);
        setPendingOrder(numericIds);
        router.put(
            reorder.url(),
            { ids: numericIds },
            {
                preserveScroll: true,
                onFinish: () => setPendingOrder(null),
            },
        );
    }

    return (
        <>
            <Head title={t('admin.faqs.orderTitle')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.faqs.orderTitle')}
                    description={t('admin.faqs.orderDescription')}
                />

                {conflict && <Alert tone="danger" title={conflict} />}
                {!canReorder && <Alert title={t('admin.faqs.orderReadOnly')} />}

                {rows.length === 0 ? (
                    <EmptyState
                        title={t('admin.faqs.orderEmptyTitle')}
                        description={t('admin.faqs.orderEmptyDescription')}
                    />
                ) : (
                    <FormSection title={t('admin.faqs.orderLabel')}>
                        <OrderableList
                            label={t('admin.faqs.orderLabel')}
                            disabled={!canReorder || pendingOrder !== null}
                            items={rows.map((item) => ({
                                id: item.id,
                                label: item.question,
                                meta: `${localeLabel(item)} · ${
                                    item.published
                                        ? t('admin.faqs.orderPublished')
                                        : t('admin.faqs.orderHidden')
                                }`,
                            }))}
                            onReorder={changeOrder}
                        />
                    </FormSection>
                )}
            </Stack>
        </>
    );
}

AdminFaqsOrder.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.faqs.title', href: index() },
        { title: 'admin.faqs.orderTitle', href: order() },
    ],
};
