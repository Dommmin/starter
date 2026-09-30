import { Head, router, usePage } from '@inertiajs/react';
import { ListTree, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    DataTable,
    EmptyState,
    FilterBar,
    FormSection,
    OrderableList,
    PageHeader,
    SelectField,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, edit, index, reorder } from '@/routes/admin/navigation';

type IndexProps = App.Data.Admin.Navigation.MenuIndexData;
type TreeItem = App.Data.Admin.Navigation.MenuTreeItemData;
type Row = { item: TreeItem; parent: TreeItem | null };

const LOCATIONS: App.Enums.MenuLocation[] = ['header', 'footer'];

/**
 * Tree of one public menu (location + language): items with their target
 * state, links to edit them and per-level ordering.
 */
export default function AdminNavigationIndex() {
    const { location, locale, locales, items, can } =
        usePage<IndexProps>().props;
    const { t } = useTranslation();
    const [isReordering, setIsReordering] = useState(false);
    const [reorderError, setReorderError] = useState<string | null>(null);

    const itemLabel = (item: TreeItem): string =>
        item.label ??
        (item.targetMissing
            ? t('admin.navigation.targetMissing')
            : t('admin.navigation.untitled', { id: item.id }));

    const rows: Row[] = items.flatMap((item) => [
        { item, parent: null },
        ...item.children.map((child) => ({ item: child, parent: item })),
    ]);

    const menuQuery = { location, locale };

    function selectMenu(next: Partial<typeof menuQuery>) {
        router.visit(index({ query: { ...menuQuery, ...next } }), {
            preserveScroll: true,
        });
    }

    function saveOrder(parentId: number | null, ids: (string | number)[]) {
        setReorderError(null);
        router.put(
            reorder.url(),
            { location, locale, parentId, ids: ids.map(Number) },
            {
                preserveScroll: true,
                onStart: () => setIsReordering(true),
                onFinish: () => setIsReordering(false),
                onError: (errors) => setReorderError(errors.conflict ?? null),
            },
        );
    }

    const orderItems = (level: TreeItem[]) =>
        level.map((item) => ({
            id: item.id,
            label: itemLabel(item),
            meta: t(`admin.navigation.types.${item.type}`),
        }));

    const levelsWithOrder = items.filter((item) => item.children.length > 1);

    return (
        <>
            <Head title={t('admin.navigation.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.navigation.title')}
                    description={t('admin.navigation.description')}
                    actions={
                        can.create ? (
                            <Button
                                variant="primary"
                                href={create({ query: menuQuery })}
                            >
                                <Plus aria-hidden="true" />
                                <span>{t('admin.navigation.create')}</span>
                            </Button>
                        ) : undefined
                    }
                />

                <FilterBar>
                    <SelectField
                        name="location"
                        label={t('admin.navigation.locationLabel')}
                        value={location}
                        onChange={(value) =>
                            selectMenu({
                                location: value as App.Enums.MenuLocation,
                            })
                        }
                        options={LOCATIONS.map((value) => ({
                            value,
                            label: t(`admin.navigation.locations.${value}`),
                        }))}
                    />
                    <SelectField
                        name="locale"
                        label={t('admin.navigation.localeLabel')}
                        value={locale}
                        onChange={(value) => selectMenu({ locale: value })}
                        options={locales.available.map((available) => ({
                            value: available.code,
                            label: available.native,
                        }))}
                    />
                </FilterBar>

                {reorderError && <Alert tone="danger" title={reorderError} />}

                <DataTable<Row>
                    caption={t('admin.navigation.tableCaption')}
                    rows={rows}
                    rowKey={(row) => row.item.id}
                    emptyState={
                        <EmptyState
                            icon={ListTree}
                            title={t('admin.navigation.emptyTitle')}
                            description={t('admin.navigation.emptyDescription')}
                        />
                    }
                    columns={[
                        {
                            key: 'label',
                            priority: 'primary',
                            header: t('admin.navigation.columns.label'),
                            render: (row) => (
                                <Stack gap="none">
                                    <Text variant="body">
                                        {itemLabel(row.item)}
                                    </Text>
                                    {row.parent && (
                                        <Text variant="caption" tone="muted">
                                            {t('admin.navigation.childMarker', {
                                                parent: itemLabel(row.parent),
                                            })}
                                        </Text>
                                    )}
                                </Stack>
                            ),
                        },
                        {
                            key: 'type',
                            priority: 'secondary',
                            header: t('admin.navigation.columns.type'),
                            render: (row) =>
                                t(`admin.navigation.types.${row.item.type}`),
                        },
                        {
                            key: 'status',
                            priority: 'status',
                            header: t('admin.navigation.columns.status'),
                            render: (row) =>
                                row.item.targetMissing ? (
                                    <Badge tone="danger">
                                        {t('admin.navigation.targetMissing')}
                                    </Badge>
                                ) : row.item.draftTarget ? (
                                    <Badge tone="outline">
                                        {t('admin.navigation.draftTarget')}
                                    </Badge>
                                ) : (
                                    <Badge tone="success">
                                        {t('admin.navigation.visible')}
                                    </Badge>
                                ),
                        },
                        {
                            key: 'actions',
                            priority: 'actions',
                            header: t('admin.navigation.columns.actions'),
                            align: 'end',
                            render: (row) => (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    href={edit(row.item.id)}
                                    ariaLabel={t(
                                        'admin.navigation.actionEditLabel',
                                        { label: itemLabel(row.item) },
                                    )}
                                >
                                    {t('admin.navigation.actionEdit')}
                                </Button>
                            ),
                        },
                    ]}
                />

                {can.reorder &&
                    (items.length > 1 || levelsWithOrder.length > 0) && (
                        <FormSection
                            title={t('admin.navigation.orderTitle')}
                            description={t('admin.navigation.orderDescription')}
                        >
                            <Stack gap="default">
                                {items.length > 1 && (
                                    <OrderableList
                                        label={t('admin.navigation.orderRoot')}
                                        items={orderItems(items)}
                                        disabled={isReordering}
                                        onReorder={(ids) =>
                                            saveOrder(null, ids)
                                        }
                                    />
                                )}
                                {levelsWithOrder.map((parent) => (
                                    <OrderableList
                                        key={parent.id}
                                        label={t(
                                            'admin.navigation.orderChildren',
                                            {
                                                parent: itemLabel(parent),
                                            },
                                        )}
                                        items={orderItems(parent.children)}
                                        disabled={isReordering}
                                        onReorder={(ids) =>
                                            saveOrder(parent.id, ids)
                                        }
                                    />
                                ))}
                            </Stack>
                        </FormSection>
                    )}
            </Stack>
        </>
    );
}

AdminNavigationIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.navigation.title', href: index() },
    ],
};
