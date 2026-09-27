import { useMemo } from 'react';
import type { RichTextImagePicker } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    picker as mediaPicker,
    preview as mediaPreview,
} from '@/routes/admin/media';

/**
 * DAM image picker for admin rich text fields: loads clean images from
 * `admin.media.picker` (JSON, same-origin session) and previews inserted
 * images through `admin.media.preview`.
 */
export function useMediaImagePicker(): RichTextImagePicker {
    const { t } = useTranslation();

    return useMemo<RichTextImagePicker>(
        () => ({
            load: async (search) => {
                const response = await fetch(
                    mediaPicker.url({ query: search ? { search } : {} }),
                    {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error(`Media picker failed: ${response.status}`);
                }

                const data =
                    (await response.json()) as App.Data.Admin.Media.MediaPickerData;

                return data.items;
            },
            previewUrl: (mediaId) => mediaPreview.url(mediaId),
            labels: {
                image: t('admin.richText.image'),
                dialogTitle: t('admin.richText.imageDialogTitle'),
                dialogDescription: t('admin.richText.imageDialogDescription'),
                searchLabel: t('admin.richText.imageSearchLabel'),
                searchPlaceholder: t('admin.richText.imageSearchPlaceholder'),
                searchClear: t('admin.richText.imageSearchClear'),
                listLabel: t('admin.richText.imageListLabel'),
                loading: t('admin.richText.imageLoading'),
                empty: t('admin.richText.imageEmpty'),
                error: t('admin.richText.imageError'),
                retry: t('admin.richText.imageRetry'),
                selectRequired: t('admin.richText.imageSelectRequired'),
                altLabel: t('admin.richText.imageAltLabel'),
                altHint: t('admin.richText.imageAltHint'),
                submit: t('admin.richText.imageSubmit'),
                cancel: t('admin.richText.imageCancel'),
                close: t('admin.richText.imageClose'),
            },
        }),
        [t],
    );
}
