import { HttpResponseError } from '@inertiajs/core';
import { useHttp } from '@inertiajs/react';
import { useRef } from 'react';
import type {
    FieldAssist,
    FieldAssistResult,
    RichTextDocument,
    RichTextNode,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { seoMeta } from '@/routes/admin/assistant';

type Suggestion = App.Data.Admin.Assistant.SeoMetaSuggestionData;

type SeoMetaInput = {
    subject: 'article' | 'page';
    locale: string;
    title: string;
    text: string;
};

/** Mirrors `SuggestSeoMetaRequest::TEXT_MAX`; longer text is cut, not rejected. */
const TEXT_MAX = 20_000;

function nodeText(node: RichTextNode): string {
    if (node.text !== undefined) {
        return node.text;
    }

    const inner = (node.content ?? []).map(nodeText).join('');

    return ['paragraph', 'heading', 'listItem', 'blockquote'].includes(
        node.type,
    )
        ? `${inner}\n`
        : inner;
}

/** Plain text of a rich text document, e.g. as assistant input. */
export function richTextToPlainText(document: RichTextDocument): string {
    return (document.content ?? [])
        .map(nodeText)
        .join('')
        .replace(/\n{2,}/g, '\n')
        .trim();
}

export type SeoMetaAssist = {
    metaDescription: FieldAssist;
    slug: FieldAssist;
};

export type SeoMetaAssistSource = {
    locale: string;
    title: string;
    body: RichTextDocument;
};

/**
 * Builds the "suggest" helpers of the meta description and slug fields of a
 * translation (one per locale tab). Both fields share one request per
 * unchanged input, so asking for the second field costs no extra call.
 * Returns `undefined` when the assistant is not available to the user.
 */
export function useSeoMetaAssist({
    enabled,
    subject,
}: {
    enabled: boolean;
    subject: SeoMetaInput['subject'];
}): ((source: SeoMetaAssistSource) => SeoMetaAssist) | undefined {
    const { t } = useTranslation();
    const http = useHttp<SeoMetaInput, Suggestion>({
        subject,
        locale: '',
        title: '',
        text: '',
    });
    const cache = useRef<{ key: string; promise: Promise<Suggestion> } | null>(
        null,
    );

    if (!enabled) {
        return undefined;
    }

    function errorMessage(error: unknown): string {
        if (!(error instanceof HttpResponseError)) {
            return t('admin.assistant.errors.generic');
        }

        if (error.response.status === 503) {
            return t('admin.assistant.errors.unavailable');
        }

        if (error.response.status === 429) {
            return error.response.data.includes('"reason":"budget"')
                ? t('admin.assistant.errors.budget')
                : t('admin.assistant.errors.rateLimited');
        }

        return t('admin.assistant.errors.generic');
    }

    function suggestion({
        locale,
        title,
        body,
    }: SeoMetaAssistSource): Promise<Suggestion> {
        const input: SeoMetaInput = {
            subject,
            locale,
            title: title.trim(),
            text: richTextToPlainText(body).slice(0, TEXT_MAX),
        };
        const key = JSON.stringify(input);

        if (cache.current?.key === key) {
            return cache.current.promise;
        }

        http.transform(() => input);
        const promise = http.post(seoMeta.url());
        cache.current = { key, promise };
        // A failed request is not cached, so the next click retries it.
        promise.catch(() => {
            if (cache.current?.promise === promise) {
                cache.current = null;
            }
        });

        return promise;
    }

    function request(
        source: SeoMetaAssistSource,
        pick: (value: Suggestion) => string,
    ): () => Promise<FieldAssistResult> {
        return async () => {
            if (source.title.trim() === '') {
                return { error: t('admin.assistant.errors.missingTitle') };
            }

            try {
                return { suggestion: pick(await suggestion(source)) };
            } catch (error) {
                return { error: errorMessage(error) };
            }
        };
    }

    const labels = {
        request: t('admin.assistant.suggest'),
        preview: t('admin.assistant.suggestion'),
        apply: t('admin.assistant.apply'),
        discard: t('admin.assistant.discard'),
    };

    return (source) => ({
        metaDescription: {
            labels,
            request: request(source, (value) => value.metaDescription),
        },
        slug: { labels, request: request(source, (value) => value.slug) },
    });
}
