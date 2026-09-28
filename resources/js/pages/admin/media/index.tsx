import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Badge,
    ConfirmDialog,
    FileDropzone,
    Link,
    MediaThumbnail,
    PageHeader,
    ResourceTable,
    Stack,
    Text,
    UploadQueue,
    type UploadQueueItem,
} from '@/design-system/primitives';
import { useMediaScanPoll } from '@/hooks/use-media-scan-poll';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import {
    destroy as mediaDestroy,
    edit as mediaEdit,
    index as mediaIndex,
    store as mediaStore,
} from '@/routes/admin/media';

type MediaRow = App.Data.Admin.Media.MediaAssetListItemData;
type QueuedUpload = UploadQueueItem & { file: File };

const statusTone = {
    quarantine: 'outline',
    clean: 'success',
    rejected: 'danger',
} as const;

function extensionOf(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

export default function AdminMediaIndex() {
    const { items, pagination, filters, can, upload } =
        usePage<App.Data.Admin.Media.MediaAssetIndexData>().props;
    const { t, formatDate, formatNumber } = useTranslation();
    const [queue, setQueue] = useState<QueuedUpload[]>([]);
    const [assetToDelete, setAssetToDelete] = useState<MediaRow | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const isUploadingRef = useRef(false);
    const maxMegabytes = Math.floor(upload.maxBytes / 1024 / 1024);

    useMediaScanPoll(
        items.some((item) => item.status === 'quarantine'),
        ['items'],
    );

    const formatSize = (bytes: number) =>
        bytes >= 1024 * 1024
            ? formatNumber(bytes / 1024 / 1024, {
                  style: 'unit',
                  unit: 'megabyte',
                  maximumFractionDigits: 1,
              })
            : formatNumber(Math.max(1, Math.round(bytes / 1024)), {
                  style: 'unit',
                  unit: 'kilobyte',
              });

    function updateItem(id: string, patch: Partial<QueuedUpload>) {
        setQueue((current) =>
            current.map((item) =>
                item.id === id ? { ...item, ...patch } : item,
            ),
        );
    }

    function addFiles(files: File[]) {
        const added = files.map((file, index): QueuedUpload => {
            const base = {
                id: `${Date.now()}-${index}-${file.name}`,
                name: file.name,
                file,
            };

            if (!upload.extensions.includes(extensionOf(file.name))) {
                return {
                    ...base,
                    status: 'error',
                    message: t('admin.media.uploadClientType'),
                };
            }

            if (file.size > upload.maxBytes) {
                return {
                    ...base,
                    status: 'error',
                    message: t('admin.media.uploadClientTooLarge', {
                        max: maxMegabytes,
                    }),
                };
            }

            return {
                ...base,
                status: 'queued',
                message: t('admin.media.uploadQueued'),
            };
        });

        setQueue((current) => [...current, ...added]);
    }

    // Upload queued files one at a time; each finished upload reloads the list.
    useEffect(() => {
        if (isUploadingRef.current) {
            return;
        }

        const next = queue.find((item) => item.status === 'queued');

        if (!next) {
            return;
        }

        isUploadingRef.current = true;
        let settled = false;
        updateItem(next.id, {
            status: 'uploading',
            progress: 0,
            message: t('admin.media.uploadUploading', { percent: 0 }),
        });

        router.post(
            mediaStore.url(),
            { file: next.file },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                async: true,
                onProgress: (progress) => {
                    const percent = Math.round(progress?.percentage ?? 0);
                    updateItem(next.id, {
                        progress: percent,
                        message: t('admin.media.uploadUploading', { percent }),
                    });
                },
                onSuccess: () => {
                    settled = true;
                    updateItem(next.id, {
                        status: 'done',
                        progress: undefined,
                        message: t('admin.media.uploadDone'),
                    });
                },
                onError: (errors) => {
                    settled = true;
                    updateItem(next.id, {
                        status: 'error',
                        progress: undefined,
                        message: errors.file ?? t('admin.media.uploadFailed'),
                    });
                },
                onHttpException: () => false,
                onNetworkError: () => false,
                onFinish: () => {
                    if (!settled) {
                        updateItem(next.id, {
                            status: 'error',
                            progress: undefined,
                            message: t('admin.media.uploadFailed'),
                        });
                    }

                    isUploadingRef.current = false;
                    setQueue((current) => [...current]);
                },
            },
        );
    }, [queue, t]);

    function confirmDelete() {
        if (!assetToDelete) {
            return;
        }

        setIsDeleting(true);
        router.delete(mediaDestroy.url(assetToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setAssetToDelete(null),
            onFinish: () => setIsDeleting(false),
        });
    }

    return (
        <>
            <Head title={t('admin.media.title')} />

            <Stack gap="default">
                <PageHeader
                    title={t('admin.media.title')}
                    description={t('admin.media.description')}
                />

                {can.create && (
                    <Stack gap="tight">
                        <FileDropzone
                            label={t('admin.media.uploadTitle')}
                            hint={t('admin.media.uploadHint', {
                                max: maxMegabytes,
                            })}
                            chooseLabel={t('admin.media.uploadChoose')}
                            accept={upload.extensions
                                .map((extension) => `.${extension}`)
                                .join(',')}
                            multiple
                            onFilesSelected={addFiles}
                        />
                        <UploadQueue
                            label={t('admin.media.uploadQueueLabel')}
                            items={queue}
                            dismissLabel={(item) =>
                                t('admin.media.uploadDismiss', {
                                    name: item.name,
                                })
                            }
                            onDismiss={(id) =>
                                setQueue((current) =>
                                    current.filter((item) => item.id !== id),
                                )
                            }
                        />
                    </Stack>
                )}

                <ResourceTable<MediaRow>
                    url={mediaIndex()}
                    rows={items}
                    rowKey={(row) => row.id}
                    pagination={pagination}
                    filters={filters}
                    searchable
                    labels={{
                        caption: t('admin.media.tableCaption'),
                        searchLabel: t('admin.media.searchLabel'),
                        searchPlaceholder: t('admin.media.searchPlaceholder'),
                        searchClear: t('admin.media.searchClear'),
                        clearFilters: t('admin.media.clearFilters'),
                        emptyTitle: t('admin.media.emptyTitle'),
                        emptyDescription: t('admin.media.emptyDescription'),
                        noResultsTitle: t('admin.media.emptyFilteredTitle'),
                        noResultsDescription: t(
                            'admin.media.emptyFilteredDescription',
                        ),
                        errorTitle: t('admin.media.errorTitle'),
                        errorRetry: t('admin.media.errorRetry'),
                        previousPage: t('admin.media.previousPage'),
                        nextPage: t('admin.media.nextPage'),
                        paginationSummary: (range) =>
                            t('admin.media.paginationSummary', range),
                    }}
                    filterFields={[
                        {
                            name: 'status',
                            label: t('admin.media.filterStatusLabel'),
                            defaultValue: 'all',
                            options: [
                                {
                                    value: 'all',
                                    label: t('admin.media.filterAll'),
                                },
                                ...(
                                    ['quarantine', 'clean', 'rejected'] as const
                                ).map((status) => ({
                                    value: status,
                                    label: t(`admin.media.status.${status}`),
                                })),
                            ],
                        },
                        {
                            name: 'type',
                            label: t('admin.media.filterTypeLabel'),
                            defaultValue: 'all',
                            options: [
                                {
                                    value: 'all',
                                    label: t('admin.media.filterAll'),
                                },
                                {
                                    value: 'image',
                                    label: t('admin.media.type.image'),
                                },
                                {
                                    value: 'document',
                                    label: t('admin.media.type.document'),
                                },
                            ],
                        },
                    ]}
                    columns={[
                        {
                            key: 'preview',
                            label: t('admin.media.columnPreview'),
                            render: (row) => (
                                <MediaThumbnail
                                    src={row.thumbnailUrl}
                                    alt=""
                                    width={row.width ?? undefined}
                                    height={row.height ?? undefined}
                                    kind={row.isImage ? 'image' : 'document'}
                                />
                            ),
                        },
                        {
                            key: 'original_name',
                            label: t('admin.media.columnName'),
                            sortable: true,
                            render: (row) => (
                                <Stack gap="none">
                                    <Link
                                        href={mediaEdit(row.id)}
                                        tone="primary"
                                    >
                                        {row.originalName}
                                    </Link>
                                    {row.isImage &&
                                        row.status === 'clean' &&
                                        !row.alt && (
                                            <Text
                                                variant="caption"
                                                tone="muted"
                                            >
                                                {t('admin.media.missingAlt')}
                                            </Text>
                                        )}
                                </Stack>
                            ),
                        },
                        {
                            key: 'status',
                            label: t('admin.media.columnStatus'),
                            render: (row) => (
                                <Stack gap="none">
                                    <Badge tone={statusTone[row.status]}>
                                        {t(`admin.media.status.${row.status}`)}
                                    </Badge>
                                    {row.status === 'quarantine' &&
                                        row.hasScanError && (
                                            <Text
                                                variant="caption"
                                                tone="muted"
                                            >
                                                {t('admin.media.scanRetrying')}
                                            </Text>
                                        )}
                                </Stack>
                            ),
                        },
                        {
                            key: 'type',
                            label: t('admin.media.columnType'),
                            render: (row) =>
                                t(
                                    row.isImage
                                        ? 'admin.media.type.image'
                                        : 'admin.media.type.document',
                                ),
                        },
                        {
                            key: 'size',
                            label: t('admin.media.columnSize'),
                            sortable: true,
                            render: (row) => formatSize(row.size),
                        },
                        {
                            key: 'created_at',
                            label: t('admin.media.columnCreatedAt'),
                            sortable: true,
                            render: (row) =>
                                row.createdAt ? formatDate(row.createdAt) : '—',
                        },
                    ]}
                    rowActions={{
                        label: (row) =>
                            t('admin.media.rowActionsLabel', {
                                name: row.originalName,
                            }),
                        items: (row) => [
                            {
                                id: 'edit',
                                label: t('admin.media.actionEdit'),
                                icon: Pencil,
                                onSelect: () =>
                                    router.visit(mediaEdit.url(row.id)),
                            },
                            ...(can.delete
                                ? [
                                      {
                                          id: 'delete',
                                          label: t('admin.media.actionDelete'),
                                          icon: Trash2,
                                          tone: 'destructive' as const,
                                          onSelect: () => setAssetToDelete(row),
                                      },
                                  ]
                                : []),
                        ],
                    }}
                />
            </Stack>

            <ConfirmDialog
                open={assetToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssetToDelete(null);
                    }
                }}
                title={t('admin.media.deleteTitle')}
                description={t('admin.media.deleteDescription')}
                confirmLabel={t('admin.media.deleteConfirm')}
                cancelLabel={t('admin.media.cancel')}
                closeLabel={t('actions.close')}
                tone="destructive"
                isPending={isDeleting}
                onConfirm={confirmDelete}
            />
        </>
    );
}

AdminMediaIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.media.title',
            href: mediaIndex(),
        },
    ],
};
