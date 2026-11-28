import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    Share2,
    Trash2,
    Unlink,
} from 'lucide-react';
import { useState } from 'react';
import {
    ActionMenu,
    Badge,
    Button,
    Card,
    DataTable,
    EmptyState,
    Inline,
    Link,
    PageHeader,
    SelectField,
    Stack,
    Tabs,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { SharedBlockList } from './shared-block-list';
import { index as adminIndex } from '@/routes/admin';
import { edit as pagesEdit, index as pagesIndex } from '@/routes/admin/pages';
import {
    destroy,
    detach,
    edit,
    index,
    reorder,
    share,
    store,
    visibility,
} from '@/routes/admin/pages/sections';

type IndexProps = App.Data.Admin.Pages.PageSectionsIndexData;
type Row = App.Data.Admin.HomeSections.HomeSectionListItemData;

export default function AdminPageSectionsIndex() {
    const {
        pageId,
        pageTitle,
        locale,
        locales,
        items,
        availableTypes,
        canUpdate,
        blocks,
        sharedSectionIds,
    } = usePage<IndexProps>().props;
    const { t } = useTranslation();
    const [newType, setNewType] = useState<string>(availableTypes[0] ?? 'hero');
    const [isCreating, setIsCreating] = useState(false);

    function shareSection(row: Row) {
        const name = row.title ?? t(`admin.homeSections.types.${row.type}`);
        router.post(
            share.url({ page: pageId, pageSection: row.id }),
            { name: name.slice(0, 120) },
            { preserveScroll: true },
        );
    }

    function detachSection(row: Row) {
        router.post(
            detach.url({ page: pageId, pageSection: row.id }),
            {},
            { preserveScroll: true },
        );
    }

    function changeLocale(nextLocale: string) {
        router.get(
            index.url(pageId, { query: { locale: nextLocale } }),
            {},
            { preserveScroll: true },
        );
    }

    function addSection() {
        setIsCreating(true);
        router.post(
            store.url(pageId),
            { locale, type: newType },
            {
                preserveScroll: true,
                onFinish: () => setIsCreating(false),
            },
        );
    }

    function toggleVisibility(row: Row) {
        router.patch(
            visibility.url({ page: pageId, pageSection: row.id }),
            { enabled: !row.enabled },
            { preserveScroll: true },
        );
    }

    function removeSection(row: Row) {
        router.delete(destroy.url({ page: pageId, pageSection: row.id }), {
            preserveScroll: true,
        });
    }

    function move(rowIndex: number, delta: -1 | 1) {
        const targetIndex = rowIndex + delta;
        if (targetIndex < 0 || targetIndex >= items.length) {
            return;
        }

        const nextOrder = items.map((item) => item.id);
        const [moved] = nextOrder.splice(rowIndex, 1);
        nextOrder.splice(targetIndex, 0, moved);

        router.put(
            reorder.url(pageId),
            { locale, order: nextOrder },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head
                title={t('admin.pages.sections.title', {
                    title: pageTitle ?? `#${pageId}`,
                })}
            />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.pages.sections.title', {
                        title: pageTitle ?? `#${pageId}`,
                    })}
                    description={t('admin.pages.sections.description')}
                    actions={
                        <Button variant="outline" href={pagesEdit(pageId)}>
                            {t('admin.pages.sections.backToPage')}
                        </Button>
                    }
                />

                <Tabs
                    ariaLabel={t('admin.pages.fields.locale')}
                    value={locale}
                    onValueChange={changeLocale}
                    items={locales.available.map((item) => ({
                        value: item.code,
                        label: item.native,
                        content: null,
                    }))}
                />

                {canUpdate && (
                    <Card>
                        <Inline gap="default" align="end">
                            <SelectField
                                name="type"
                                label={t('admin.pages.sections.addTypeLabel')}
                                value={newType}
                                onChange={setNewType}
                                options={availableTypes.map((type) => ({
                                    value: type,
                                    label: t(
                                        `admin.homeSections.types.${type}`,
                                    ),
                                }))}
                            />
                            <Button isPending={isCreating} onClick={addSection}>
                                <Plus aria-hidden="true" />
                                {t('admin.pages.sections.add')}
                            </Button>
                        </Inline>
                    </Card>
                )}

                {blocks.length > 0 && (
                    <Card>
                        <SharedBlockList
                            pageId={pageId}
                            blocks={blocks}
                            canUpdate={canUpdate}
                        />
                    </Card>
                )}

                <DataTable<Row>
                    caption={t('admin.pages.sections.tableCaption')}
                    rows={items}
                    rowKey={(row) => row.id}
                    emptyState={
                        <EmptyState
                            title={t('admin.pages.sections.emptyTitle')}
                            description={t(
                                'admin.pages.sections.emptyDescription',
                            )}
                        />
                    }
                    columns={[
                        {
                            key: 'type',
                            priority: 'primary',
                            header: t('admin.homeSections.columnSection'),
                            render: (row) => (
                                <Link
                                    href={edit({
                                        page: pageId,
                                        pageSection: row.id,
                                    })}
                                    tone="primary"
                                >
                                    {t(`admin.homeSections.types.${row.type}`)}
                                </Link>
                            ),
                        },
                        {
                            key: 'title',
                            priority: 'secondary',
                            header: t('admin.pages.sections.columnHeading'),
                            render: (row) => (
                                <Inline gap="tight">
                                    {row.title ?? '—'}
                                    {sharedSectionIds.includes(row.id) && (
                                        <Badge tone="neutral">
                                            {t('admin.pages.blocks.badge')}
                                        </Badge>
                                    )}
                                </Inline>
                            ),
                        },
                        {
                            key: 'enabled',
                            priority: 'status',
                            header: t('admin.pages.sections.columnVisibility'),
                            render: (row) => (
                                <Badge
                                    tone={row.enabled ? 'success' : 'neutral'}
                                >
                                    {row.enabled
                                        ? t('admin.pages.sections.visible')
                                        : t('admin.pages.sections.hiddenState')}
                                </Badge>
                            ),
                        },
                        {
                            key: 'actions',
                            priority: 'actions',
                            header: t('admin.pages.sections.columnActions'),
                            align: 'end',
                            render: (row) => {
                                const rowIndex = items.findIndex(
                                    (item) => item.id === row.id,
                                );

                                return (
                                    <ActionMenu
                                        triggerLabel={t(
                                            'admin.pages.sections.rowActionsLabel',
                                            {
                                                section: t(
                                                    `admin.homeSections.types.${row.type}`,
                                                ),
                                            },
                                        )}
                                        items={[
                                            {
                                                id: 'edit',
                                                label: t(
                                                    'admin.pages.sections.actionEdit',
                                                ),
                                                icon: Pencil,
                                                onSelect: () =>
                                                    router.visit(
                                                        edit.url({
                                                            page: pageId,
                                                            pageSection: row.id,
                                                        }),
                                                    ),
                                            },
                                            ...(canUpdate
                                                ? [
                                                      {
                                                          id: 'toggle',
                                                          label: row.enabled
                                                              ? t(
                                                                    'admin.pages.sections.actionHide',
                                                                )
                                                              : t(
                                                                    'admin.pages.sections.actionShow',
                                                                ),
                                                          icon: row.enabled
                                                              ? EyeOff
                                                              : Eye,
                                                          onSelect: () =>
                                                              toggleVisibility(
                                                                  row,
                                                              ),
                                                      },
                                                      ...(rowIndex > 0
                                                          ? [
                                                                {
                                                                    id: 'up',
                                                                    label: t(
                                                                        'admin.pages.sections.moveUp',
                                                                    ),
                                                                    icon: ArrowUp,
                                                                    onSelect:
                                                                        () =>
                                                                            move(
                                                                                rowIndex,
                                                                                -1,
                                                                            ),
                                                                },
                                                            ]
                                                          : []),
                                                      ...(rowIndex <
                                                      items.length - 1
                                                          ? [
                                                                {
                                                                    id: 'down',
                                                                    label: t(
                                                                        'admin.pages.sections.moveDown',
                                                                    ),
                                                                    icon: ArrowDown,
                                                                    onSelect:
                                                                        () =>
                                                                            move(
                                                                                rowIndex,
                                                                                1,
                                                                            ),
                                                                },
                                                            ]
                                                          : []),
                                                      sharedSectionIds.includes(
                                                          row.id,
                                                      )
                                                          ? {
                                                                id: 'detach',
                                                                label: t(
                                                                    'admin.pages.blocks.detach',
                                                                ),
                                                                icon: Unlink,
                                                                onSelect: () =>
                                                                    detachSection(
                                                                        row,
                                                                    ),
                                                            }
                                                          : {
                                                                id: 'share',
                                                                label: t(
                                                                    'admin.pages.blocks.share',
                                                                ),
                                                                icon: Share2,
                                                                onSelect: () =>
                                                                    shareSection(
                                                                        row,
                                                                    ),
                                                            },
                                                      {
                                                          id: 'delete',
                                                          label: t(
                                                              'admin.pages.sections.delete',
                                                          ),
                                                          icon: Trash2,
                                                          tone: 'destructive' as const,
                                                          onSelect: () =>
                                                              removeSection(
                                                                  row,
                                                              ),
                                                      },
                                                  ]
                                                : []),
                                        ]}
                                    />
                                );
                            },
                        },
                    ]}
                />
            </Stack>
        </>
    );
}

AdminPageSectionsIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.pages.title', href: pagesIndex() },
    ],
};
