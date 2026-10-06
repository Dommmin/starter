import { useMemo, useState } from 'react';
import {
    Button,
    FileDropzone,
    Image,
    ImagePickerField,
    MediaGrid,
    MediaThumbnail,
    RichTextField,
    Stack,
    Text,
    UploadQueue,
    type ImagePickerFieldLabels,
    type MediaGridItem,
    type RichTextDocument,
    type RichTextFieldLabels,
    type RichTextImageOption,
    type RichTextImagePicker,
    type UploadQueueItem,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as mediaIndex } from '@/routes/admin/media';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

/** Existing public assets stand in for DAM variants; nothing is uploaded. */
const demoImageSrc = '/apple-touch-icon.png';
const demoVectorSrc = '/favicon.svg';
const demoBrokenSrc = '/design-system-demo-missing-image.png';

const richTextLabelKeys = [
    'toolbar',
    'heading2',
    'heading3',
    'heading4',
    'bold',
    'italic',
    'strike',
    'code',
    'bulletList',
    'orderedList',
    'blockquote',
    'horizontalRule',
    'link',
    'unlink',
    'linkDialogTitle',
    'linkUrlLabel',
    'linkUrlHint',
    'linkSubmit',
    'linkCancel',
    'linkClose',
    'linkInvalid',
] as const satisfies readonly (keyof RichTextFieldLabels)[];

const imageLabelKeys = {
    image: 'image',
    dialogTitle: 'imageDialogTitle',
    dialogDescription: 'imageDialogDescription',
    searchLabel: 'imageSearchLabel',
    searchPlaceholder: 'imageSearchPlaceholder',
    searchClear: 'imageSearchClear',
    listLabel: 'imageListLabel',
    loading: 'imageLoading',
    empty: 'imageEmpty',
    error: 'imageError',
    retry: 'imageRetry',
    selectRequired: 'imageSelectRequired',
    altLabel: 'imageAltLabel',
    altHint: 'imageAltHint',
    submit: 'imageSubmit',
    cancel: 'imageCancel',
    close: 'imageClose',
} as const;

type DemoPickerMode = 'ready' | 'slow' | 'empty' | 'failing';

function paragraph(text: string): RichTextDocument {
    return {
        type: 'doc',
        content: [{ type: 'paragraph', content: [{ type: 'text', text }] }],
    };
}

/** ADM-06 — content and media, with a synthetic in-browser DAM picker. */
function ContentMediaSection() {
    const { t } = useTranslation();
    const demo = (key: string, params?: Record<string, string>) =>
        t(`admin.designSystem.contentMedia.${key}`, params);

    const richTextLabels = Object.fromEntries(
        richTextLabelKeys.map((key) => [key, t(`admin.richText.${key}`)]),
    ) as RichTextFieldLabels;

    const demoImages = useMemo<RichTextImageOption[]>(() => {
        const option = (
            id: number,
            nameKey: string,
            thumbnailUrl: string,
            size: number,
        ): RichTextImageOption => ({
            id,
            name: t(`admin.designSystem.contentMedia.imageNames.${nameKey}`),
            alt: t('admin.designSystem.contentMedia.imageAlt'),
            thumbnailUrl,
            width: size,
            height: size,
        });

        return [
            option(1, 'logo', demoImageSrc, 180),
            option(2, 'vector', demoVectorSrc, 48),
            option(3, 'long', demoImageSrc, 180),
        ];
    }, [t]);

    const pickers = useMemo(() => {
        const labels = Object.fromEntries(
            Object.entries(imageLabelKeys).map(([key, translationKey]) => [
                key,
                t(`admin.richText.${translationKey}`),
            ]),
        ) as RichTextImagePicker['labels'];

        const create = (mode: DemoPickerMode): RichTextImagePicker => ({
            load: (search) => {
                if (mode === 'failing') {
                    return Promise.reject(
                        new Error('Synthetic picker failure'),
                    );
                }

                const matching =
                    mode === 'empty'
                        ? []
                        : demoImages.filter((image) =>
                              image.name
                                  .toLocaleLowerCase()
                                  .includes(search.toLocaleLowerCase()),
                          );

                return new Promise((resolve) =>
                    window.setTimeout(
                        () => resolve(matching),
                        mode === 'slow' ? 4000 : 150,
                    ),
                );
            },
            previewUrl: (mediaId) =>
                demoImages.find((image) => image.id === mediaId)
                    ?.thumbnailUrl ?? demoImageSrc,
            labels,
        });

        return {
            ready: create('ready'),
            slow: create('slow'),
            empty: create('empty'),
            failing: create('failing'),
        };
    }, [t, demoImages]);

    const pickerLabels: ImagePickerFieldLabels = {
        choose: demo('picker.choose'),
        change: demo('picker.change'),
        remove: demo('picker.remove'),
        empty: demo('picker.empty'),
        preview: demo('picker.preview'),
    };

    const [richText, setRichText] = useState<RichTextDocument>(() =>
        paragraph(demo('richTextValue')),
    );
    const [emptyRichText, setEmptyRichText] = useState<RichTextDocument>({
        type: 'doc',
    });
    const [longRichText, setLongRichText] = useState<RichTextDocument>(() => ({
        type: 'doc',
        content: [
            {
                type: 'heading',
                attrs: { level: 2 },
                content: [{ type: 'text', text: demo('longTitle') }],
            },
            ...Array.from({ length: 3 }, () => ({
                type: 'paragraph',
                content: [{ type: 'text', text: demo('longParagraph') }],
            })),
        ],
    }));
    const [coverId, setCoverId] = useState('');
    const [chosenCoverId, setChosenCoverId] = useState('1');
    const [selectedMediaId, setSelectedMediaId] = useState<number | null>(1);
    const [selectedFiles, setSelectedFiles] = useState<UploadQueueItem[]>([]);

    const mediaGridItems: MediaGridItem[] = demoImages.map(
        ({ id, name, thumbnailUrl, width, height }) => ({
            id,
            name,
            thumbnailUrl,
            width,
            height,
        }),
    );

    const queueItems: UploadQueueItem[] = [
        {
            id: 'queued',
            name: demo('files.queued'),
            status: 'queued',
            message: demo('queue.queued'),
        },
        {
            id: 'uploading',
            name: demo('files.uploading'),
            status: 'uploading',
            progress: 40,
            message: demo('queue.uploading', { progress: '40' }),
        },
        {
            id: 'done',
            name: demo('files.done'),
            status: 'done',
            message: demo('queue.done'),
        },
        {
            id: 'error',
            name: demo('files.long'),
            status: 'error',
            message: demo('queue.error'),
        },
    ];
    const [queue, setQueue] = useState(queueItems);
    const noop = () => {};

    return (
        <Stack gap="default">
            <Stack gap="tight" align="start">
                <Text tone="muted">{demo('syntheticNote')}</Text>
                <Button variant="link" href={mediaIndex()}>
                    {demo('realScreen')}
                </Button>
            </Stack>

            <ShowcaseComponent
                name="RichTextField"
                layout="wide"
                notApplicable={['pending', 'success', 'noMedia']}
            >
                <ShowcaseState state="default" fill>
                    <RichTextField
                        id="design-system-rich-text"
                        name="demo_body"
                        label={demo('richTextLabel')}
                        hint={demo('richTextHint')}
                        value={richText}
                        onChange={setRichText}
                        labels={richTextLabels}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="withMedia"
                    detail={demo('pickerDetail')}
                    fill
                >
                    <RichTextField
                        id="design-system-rich-text-images"
                        name="demo_body_images"
                        label={demo('richTextLabel')}
                        value={richText}
                        onChange={setRichText}
                        labels={richTextLabels}
                        imagePicker={pickers.ready}
                    />
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <RichTextField
                        id="design-system-rich-text-empty"
                        name="demo_body_empty"
                        label={demo('richTextLabel')}
                        required
                        value={emptyRichText}
                        onChange={setEmptyRichText}
                        labels={richTextLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <RichTextField
                        id="design-system-rich-text-error"
                        name="demo_body_error"
                        label={demo('richTextLabel')}
                        error={demo('richTextError')}
                        required
                        value={emptyRichText}
                        onChange={setEmptyRichText}
                        labels={richTextLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <RichTextField
                        id="design-system-rich-text-disabled"
                        name="demo_body_disabled"
                        label={demo('richTextLabel')}
                        disabled
                        value={richText}
                        onChange={noop}
                        labels={richTextLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <RichTextField
                        id="design-system-rich-text-long"
                        name="demo_body_long"
                        label={demo('longTitle')}
                        value={longRichText}
                        onChange={setLongRichText}
                        labels={richTextLabels}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="ImagePickerField"
                layout="wide"
                notApplicable={['pending', 'success', 'longContent']}
            >
                <ShowcaseState state="noMedia" fill>
                    <ImagePickerField
                        id="design-system-cover-empty"
                        name="demo_cover_empty"
                        label={demo('pickerLabel')}
                        hint={demo('pickerHint')}
                        value={coverId}
                        onChange={setCoverId}
                        picker={pickers.ready}
                        labels={pickerLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="withMedia" fill>
                    <ImagePickerField
                        id="design-system-cover"
                        name="demo_cover"
                        label={demo('pickerLabel')}
                        value={chosenCoverId}
                        onChange={setChosenCoverId}
                        picker={pickers.ready}
                        labels={pickerLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="loading" detail={demo('dialog')} fill>
                    <ImagePickerField
                        id="design-system-cover-slow"
                        name="demo_cover_slow"
                        label={demo('pickerLabel')}
                        value=""
                        onChange={noop}
                        picker={pickers.slow}
                        labels={pickerLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="empty" detail={demo('dialog')} fill>
                    <ImagePickerField
                        id="design-system-cover-no-results"
                        name="demo_cover_no_results"
                        label={demo('pickerLabel')}
                        value=""
                        onChange={noop}
                        picker={pickers.empty}
                        labels={pickerLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <ImagePickerField
                        id="design-system-cover-error"
                        name="demo_cover_error"
                        label={demo('pickerLabel')}
                        error={demo('pickerError')}
                        required
                        value=""
                        onChange={noop}
                        picker={pickers.failing}
                        labels={pickerLabels}
                    />
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <ImagePickerField
                        id="design-system-cover-disabled"
                        name="demo_cover_disabled"
                        label={demo('pickerLabel')}
                        disabled
                        value="1"
                        onChange={noop}
                        picker={pickers.ready}
                        labels={pickerLabels}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="MediaGrid · MediaThumbnail"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'error',
                    'success',
                ]}
            >
                <ShowcaseState state="default" detail="MediaGrid" fill>
                    <MediaGrid
                        label={demo('gridLabel')}
                        name="demo_media"
                        items={mediaGridItems}
                        selectedId={selectedMediaId}
                        onSelect={setSelectedMediaId}
                    />
                </ShowcaseState>
                <ShowcaseState state="empty" detail="MediaGrid" fill>
                    <Text variant="caption" tone="muted">
                        {demo('gridEmptyNote')}
                    </Text>
                </ShowcaseState>
                <ShowcaseState state="withMedia" detail="MediaThumbnail">
                    <Stack gap="tight" align="start">
                        <MediaThumbnail
                            src={demoImageSrc}
                            alt={demo('imageAlt')}
                            size="sm"
                        />
                        <MediaThumbnail
                            src={demoImageSrc}
                            alt={demo('imageAlt')}
                            size="md"
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="noMedia" detail="MediaThumbnail">
                    <Stack gap="tight" align="start">
                        <MediaThumbnail
                            src={null}
                            alt={demo('quarantined')}
                            kind="image"
                            size="md"
                        />
                        <MediaThumbnail
                            src={null}
                            alt={demo('document')}
                            kind="document"
                            size="md"
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="brokenMedia" detail="MediaThumbnail">
                    <MediaThumbnail
                        src={demoBrokenSrc}
                        alt={demo('imageAlt')}
                        size="md"
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Image"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'longContent',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" detail="fit=intrinsic">
                    <Image
                        src={demoImageSrc}
                        width={180}
                        height={180}
                        alt={demo('imageAlt')}
                    />
                </ShowcaseState>
                <ShowcaseState state="variants" detail="fit=cover">
                    <Image
                        src={demoVectorSrc}
                        width={48}
                        height={48}
                        alt={demo('imageAlt')}
                        fit="cover"
                    />
                </ShowcaseState>
                <ShowcaseState state="decorative" detail='alt=""'>
                    <Image src={demoVectorSrc} width={48} height={48} alt="" />
                </ShowcaseState>
                <ShowcaseState state="brokenMedia">
                    <Image
                        src={demoBrokenSrc}
                        width={180}
                        height={180}
                        alt={demo('imageAlt')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="FileDropzone · UploadQueue"
                layout="wide"
                notApplicable={['noMedia']}
            >
                <ShowcaseState state="default" detail="FileDropzone" fill>
                    <Stack gap="tight">
                        <FileDropzone
                            label={demo('dropzoneLabel')}
                            hint={demo('dropzoneHint')}
                            chooseLabel={demo('dropzoneChoose')}
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            multiple
                            onFilesSelected={(files) =>
                                setSelectedFiles(
                                    files.map((file, index) => ({
                                        id: `${index}-${file.name}`,
                                        name: file.name,
                                        status: 'queued',
                                        message: demo('queue.notSent'),
                                    })),
                                )
                            }
                        />
                        <UploadQueue
                            label={demo('queueLabel')}
                            items={selectedFiles}
                            dismissLabel={(item) =>
                                demo('queueDismiss', { name: item.name })
                            }
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="disabled" detail="FileDropzone" fill>
                    <FileDropzone
                        label={demo('dropzoneLabel')}
                        hint={demo('dropzoneHint')}
                        chooseLabel={demo('dropzoneChoose')}
                        disabled
                        onFilesSelected={noop}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="pending"
                    detail={`UploadQueue · ${demo('queueStates')}`}
                    fill
                >
                    {queue.length > 0 ? (
                        <UploadQueue
                            label={demo('queueLabel')}
                            items={queue}
                            dismissLabel={(item) =>
                                demo('queueDismiss', { name: item.name })
                            }
                            onDismiss={(id) =>
                                setQueue((items) =>
                                    items.filter((item) => item.id !== id),
                                )
                            }
                        />
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setQueue(queueItems)}
                        >
                            {demo('restoreQueue')}
                        </Button>
                    )}
                </ShowcaseState>
                <ShowcaseState state="empty" detail="UploadQueue" fill>
                    <Text variant="caption" tone="muted">
                        {demo('queueEmptyNote')}
                    </Text>
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const contentMediaFamily: ShowcaseFamily = {
    id: 'adm-06',
    titleKey: 'admin.designSystem.contentMedia.title',
    descriptionKey: 'admin.designSystem.contentMedia.description',
    Component: ContentMediaSection,
};
