import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    ConfirmDialog,
    DataTable,
    DescriptionList,
    EmptyState,
    FormActions,
    Grid,
    Heading,
    Icon,
    Image,
    Link,
    PageHeader,
    Stack,
    Surface,
    Text,
    TextareaField,
    TextField,
} from '@/design-system/primitives';
import { useMediaScanPoll } from '@/hooks/use-media-scan-poll';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import {
    destroy as mediaDestroy,
    index as mediaIndex,
    update as mediaUpdate,
} from '@/routes/admin/media';

type Variant = App.Data.Admin.Media.MediaVariantData;

const statusTone = {
    quarantine: 'outline',
    clean: 'success',
    rejected: 'danger',
} as const;

export default function AdminMediaEdit() {
    const { asset, can } =
        usePage<App.Data.Admin.Media.MediaAssetEditorData>().props;
    const { t, formatDate, formatNumber } = useTranslation();
    const form = useForm({
        alt: asset.alt ?? '',
        updated_at: asset.updatedAt ?? '',
    });
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const errors = form.errors as Partial<Record<string, string>>;

    useMediaScanPoll(asset.status === 'quarantine', ['asset']);

    // The scan result bumps `updated_at`; adopt it so saving the alt text
    // afterwards is not rejected as a conflicting edit.
    const renderedStatus = useRef(asset.status);
    useEffect(() => {
        if (renderedStatus.current === asset.status) {
            return;
        }

        renderedStatus.current = asset.status;
        form.setData('updated_at', asset.updatedAt ?? '');
    });

    const formatSize = (bytes: number) =>
        formatNumber(bytes / 1024, {
            style: 'unit',
            unit: 'kilobyte',
            maximumFractionDigits: 1,
        });

    function submit() {
        form.put(mediaUpdate.url(asset.id), { preserveScroll: true });
    }

    function confirmDelete() {
        setIsDeleting(true);
        router.delete(mediaDestroy.url(asset.id), {
            onFinish: () => setIsDeleting(false),
        });
    }

    return (
        <>
            <Head title={asset.originalName} />

            <Stack gap="default">
                <PageHeader
                    title={asset.originalName}
                    description={t('admin.media.detailsTitle')}
                    badge={
                        <Badge tone={statusTone[asset.status]}>
                            {t(`admin.media.status.${asset.status}`)}
                        </Badge>
                    }
                    actions={
                        <Button variant="outline" href={mediaIndex()}>
                            <Icon icon={ArrowLeft} />
                            {t('admin.media.backToList')}
                        </Button>
                    }
                />

                {asset.status === 'quarantine' && (
                    <Alert
                        tone="neutral"
                        title={t('admin.media.quarantineNotice')}
                        description={
                            asset.scanError
                                ? t('admin.media.scanErrorNotice')
                                : undefined
                        }
                    />
                )}
                {asset.status === 'rejected' && (
                    <Alert
                        tone="danger"
                        title={t('admin.media.rejectedNotice')}
                        description={asset.scanError ?? undefined}
                    />
                )}

                <Grid layout="split" gap="default">
                    <Surface padding="compact">
                        <Stack gap="tight">
                            <Heading level={2} variant="subsection">
                                {t('admin.media.previewTitle')}
                            </Heading>
                            {asset.image ? (
                                <Image
                                    src={asset.image.src}
                                    srcset={asset.image.srcset}
                                    sources={asset.image.sources}
                                    width={asset.image.width}
                                    height={asset.image.height}
                                    sizes="(min-width: 768px) 50vw, 100vw"
                                    alt={asset.alt ?? ''}
                                />
                            ) : (
                                <EmptyState
                                    title={t('admin.media.noPreview')}
                                />
                            )}
                        </Stack>
                    </Surface>

                    <Stack gap="default">
                        <DescriptionList
                            items={[
                                {
                                    id: 'fileName',
                                    label: t('admin.media.fields.fileName'),
                                    value: asset.originalName,
                                },
                                {
                                    id: 'type',
                                    label: t('admin.media.fields.type'),
                                    value: asset.mime,
                                },
                                {
                                    id: 'size',
                                    label: t('admin.media.fields.size'),
                                    value: formatSize(asset.size),
                                },
                                {
                                    id: 'dimensions',
                                    label: t('admin.media.fields.dimensions'),
                                    value:
                                        asset.width && asset.height
                                            ? t(
                                                  'admin.media.fields.dimensionsValue',
                                                  {
                                                      width: asset.width,
                                                      height: asset.height,
                                                  },
                                              )
                                            : null,
                                },
                                {
                                    id: 'uploadedBy',
                                    label: t('admin.media.fields.uploadedBy'),
                                    value: asset.ownerName,
                                },
                                {
                                    id: 'uploadedAt',
                                    label: t('admin.media.fields.uploadedAt'),
                                    value: asset.createdAt
                                        ? formatDate(asset.createdAt)
                                        : null,
                                },
                            ]}
                        />

                        <TextField
                            name="checksum"
                            label={t('admin.media.fields.checksum')}
                            description={t('admin.media.fields.checksumHelp')}
                            value={asset.checksum}
                            readOnly
                        />

                        {asset.downloadUrl && (
                            <Link
                                href={asset.downloadUrl}
                                external
                                tone="primary"
                            >
                                <Icon icon={Download} />
                                {t('admin.media.download')}
                            </Link>
                        )}

                        {asset.isImage && can.update && (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    submit();
                                }}
                            >
                                <Stack gap="tight">
                                    {errors.conflict && (
                                        <Alert
                                            tone="danger"
                                            title={errors.conflict}
                                        />
                                    )}
                                    <TextareaField
                                        name="alt"
                                        label={t('admin.media.fields.alt')}
                                        description={t('admin.media.altHint')}
                                        value={form.data.alt}
                                        onChange={(value) =>
                                            form.setData('alt', value)
                                        }
                                        error={errors.alt}
                                        rows={3}
                                    />
                                    <FormActions align="between">
                                        {can.delete ? (
                                            <Button
                                                variant="destructive"
                                                onClick={() =>
                                                    setIsDeleteOpen(true)
                                                }
                                            >
                                                <Icon icon={Trash2} />
                                                {t('admin.media.delete')}
                                            </Button>
                                        ) : (
                                            <span />
                                        )}
                                        <Button
                                            type="submit"
                                            isPending={form.processing}
                                        >
                                            {t('admin.media.save')}
                                        </Button>
                                    </FormActions>
                                </Stack>
                            </form>
                        )}

                        {!(asset.isImage && can.update) && can.delete && (
                            <FormActions align="start">
                                <Button
                                    variant="destructive"
                                    onClick={() => setIsDeleteOpen(true)}
                                >
                                    <Icon icon={Trash2} />
                                    {t('admin.media.delete')}
                                </Button>
                            </FormActions>
                        )}
                    </Stack>
                </Grid>

                {asset.isImage && (
                    <Stack gap="tight">
                        <Heading level={2} variant="subsection">
                            {t('admin.media.variantsTitle')}
                        </Heading>
                        <Text tone="muted">
                            {t('admin.media.variantsDescription')}
                        </Text>
                        <DataTable<Variant>
                            caption={t('admin.media.variantsCaption')}
                            emptyState={
                                <EmptyState
                                    title={t('admin.media.variantsEmpty')}
                                />
                            }
                            rows={asset.variants}
                            rowKey={(variant) => variant.url}
                            columns={[
                                {
                                    key: 'format',
                                    priority: 'primary',
                                    header: t('admin.media.variantFormat'),
                                    render: (variant) => (
                                        <Link
                                            href={variant.url}
                                            external
                                            tone="primary"
                                        >
                                            {variant.format.toUpperCase()}
                                        </Link>
                                    ),
                                },
                                {
                                    key: 'dimensions',
                                    priority: 'secondary',
                                    header: t('admin.media.variantDimensions'),
                                    render: (variant) =>
                                        t(
                                            'admin.media.fields.dimensionsValue',
                                            {
                                                width: variant.width,
                                                height: variant.height,
                                            },
                                        ),
                                },
                                {
                                    key: 'size',
                                    priority: 'secondary',
                                    header: t('admin.media.variantSize'),
                                    align: 'end',
                                    render: (variant) =>
                                        formatSize(variant.size),
                                },
                            ]}
                        />
                    </Stack>
                )}
            </Stack>

            <ConfirmDialog
                open={isDeleteOpen}
                onOpenChange={setIsDeleteOpen}
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

AdminMediaEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.media.title', href: mediaIndex() },
    ],
};
