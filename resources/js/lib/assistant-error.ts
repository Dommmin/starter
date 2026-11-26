import { HttpResponseError } from '@inertiajs/core';
import type { TranslationFunction } from '@/i18n/types';

/**
 * Translated message of a failed assistant request; `notFound` covers the
 * endpoints that answer 404 for a record the assistant cannot handle.
 */
export function assistantErrorMessage(
    error: unknown,
    t: TranslationFunction,
    notFound = 'admin.assistant.errors.generic',
): string {
    if (!(error instanceof HttpResponseError)) {
        return t('admin.assistant.errors.generic');
    }

    switch (error.response.status) {
        case 503:
            return t('admin.assistant.errors.unavailable');
        case 429:
            return error.response.data.includes('"reason":"budget"')
                ? t('admin.assistant.errors.budget')
                : t('admin.assistant.errors.rateLimited');
        case 404:
            return t(notFound);
        default:
            return t('admin.assistant.errors.generic');
    }
}
