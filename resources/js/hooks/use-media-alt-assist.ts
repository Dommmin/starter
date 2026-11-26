import { useHttp } from '@inertiajs/react';
import type { FieldAssist } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { assistantErrorMessage } from '@/lib/assistant-error';
import { mediaAltText } from '@/routes/admin/assistant';

type Suggestion = App.Data.Admin.Assistant.MediaAltTextSuggestionData;

/**
 * Builds the "suggest" helper of the alt text field of a media asset; the
 * image itself is read on the server. Returns `undefined` when the
 * assistant is not available for this asset and user.
 */
export function useMediaAltAssist({
    enabled,
    mediaAssetId,
}: {
    enabled: boolean;
    mediaAssetId: number;
}): FieldAssist | undefined {
    const { t } = useTranslation();
    const http = useHttp<Record<string, never>, Suggestion>({});

    if (!enabled) {
        return undefined;
    }

    return {
        labels: {
            request: t('admin.assistant.suggestAlt'),
            preview: t('admin.assistant.suggestion'),
            apply: t('admin.assistant.apply'),
            discard: t('admin.assistant.discard'),
        },
        request: async () => {
            try {
                const { alt } = await http.post(mediaAltText.url(mediaAssetId));

                return { suggestion: alt };
            } catch (error) {
                return {
                    error: assistantErrorMessage(
                        error,
                        t,
                        'admin.assistant.errors.notDescribable',
                    ),
                };
            }
        },
    };
}
