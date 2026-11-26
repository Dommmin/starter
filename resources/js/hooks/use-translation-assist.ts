import { HttpResponseError } from '@inertiajs/core';
import { useHttp } from '@inertiajs/react';
import type { RichTextDocument } from '@/design-system/primitives';
import { assistantErrorMessage } from '@/lib/assistant-error';
import { useTranslation } from '@/i18n';
import { translation } from '@/routes/admin/assistant';

type Suggestion = App.Data.Admin.Assistant.TranslationSuggestionData;

type TranslationInput = {
    subject: 'article' | 'page';
    source_locale: string;
    target_locale: string;
    title: string;
    excerpt: string;
    meta_description: string;
    body: string;
};

/** Source language version sent for translation (the default locale tab). */
export type TranslationAssistSource = {
    title: string;
    excerpt?: string;
    metaDescription: string;
    body: RichTextDocument;
};

/** Draft of the target tab; fields empty in the source are empty here. */
export type TranslationDraft = {
    title: string;
    excerpt: string;
    metaDescription: string;
    body: RichTextDocument;
};

export type TranslationAssistResult =
    | { draft: TranslationDraft }
    | { error: string };

const EMPTY_DOCUMENT: RichTextDocument = { type: 'doc', content: [] };

function isDocument(value: unknown): value is RichTextDocument {
    return (
        typeof value === 'object' &&
        value !== null &&
        'type' in value &&
        value.type === 'doc'
    );
}

/** First validation message of a 422 answer (e.g. text too long). */
function validationMessage(error: HttpResponseError): string | null {
    try {
        const parsed: unknown = JSON.parse(error.response.data);

        if (
            typeof parsed === 'object' &&
            parsed !== null &&
            'message' in parsed &&
            typeof parsed.message === 'string'
        ) {
            const errors =
                'errors' in parsed &&
                typeof parsed.errors === 'object' &&
                parsed.errors !== null
                    ? Object.values(parsed.errors).flat()
                    : [];
            const first: unknown = errors[0];

            return typeof first === 'string' ? first : parsed.message;
        }
    } catch {
        // Not JSON: fall back to the generic message.
    }

    return null;
}

/**
 * Translates the default language version of a page or article into
 * another locale tab. The answer is a draft the form copies into the tab;
 * nothing is saved. Returns `undefined` when the assistant is not
 * available to the user.
 */
export function useTranslationAssist({
    enabled,
    subject,
    sourceLocale,
}: {
    enabled: boolean;
    subject: TranslationInput['subject'];
    sourceLocale: string;
}):
    | ((
          targetLocale: string,
          source: TranslationAssistSource,
      ) => Promise<TranslationAssistResult>)
    | undefined {
    const { t } = useTranslation();
    const http = useHttp<TranslationInput, Suggestion>({
        subject,
        source_locale: sourceLocale,
        target_locale: '',
        title: '',
        excerpt: '',
        meta_description: '',
        body: '',
    });

    if (!enabled) {
        return undefined;
    }

    return async (targetLocale, source) => {
        http.transform(() => ({
            subject,
            source_locale: sourceLocale,
            target_locale: targetLocale,
            title: source.title.trim(),
            ...(subject === 'article' ? { excerpt: source.excerpt ?? '' } : {}),
            meta_description: source.metaDescription,
            body: source.body,
        }));

        try {
            const suggestion = await http.post(translation.url());

            return {
                draft: {
                    title: suggestion.title,
                    excerpt: suggestion.excerpt ?? '',
                    metaDescription: suggestion.metaDescription,
                    body: isDocument(suggestion.body)
                        ? suggestion.body
                        : EMPTY_DOCUMENT,
                },
            };
        } catch (error) {
            if (
                error instanceof HttpResponseError &&
                error.response.status === 422
            ) {
                return {
                    error:
                        validationMessage(error) ??
                        t('admin.assistant.errors.generic'),
                };
            }

            return { error: assistantErrorMessage(error, t) };
        }
    };
}
