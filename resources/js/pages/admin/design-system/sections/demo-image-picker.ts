import { useMemo } from 'react';
import type {
    ImagePickerFieldLabels,
    RichTextImageOption,
    RichTextImagePicker,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

/** Existing public assets stand in for DAM variants; nothing is uploaded. */
export const demoImageSrc = '/apple-touch-icon.png';
export const demoVectorSrc = '/favicon.svg';
export const demoBrokenSrc = '/design-system-demo-missing-image.png';

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

/**
 * Synthetic in-browser DAM shared by the media and form demos: three public
 * images, pickers that resolve, stay loading, find nothing or fail, and the
 * `ImagePickerField` labels.
 */
export function useDemoImagePicker() {
    const { t } = useTranslation();

    const demoImages = useMemo<RichTextImageOption[]>(() => {
        const option = (
            id: number,
            nameKey: string,
            thumbnailUrl: string,
            size: number,
        ): RichTextImageOption => ({
            id,
            name: t(`showcase.contentMedia.imageNames.${nameKey}`),
            alt: t('showcase.contentMedia.imageAlt'),
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

    const pickerLabels = useMemo<ImagePickerFieldLabels>(() => {
        const label = (key: string) => t(`showcase.contentMedia.picker.${key}`);

        return {
            choose: label('choose'),
            change: label('change'),
            remove: label('remove'),
            empty: label('empty'),
            preview: label('preview'),
        };
    }, [t]);

    return { demoImages, pickers, pickerLabels };
}
