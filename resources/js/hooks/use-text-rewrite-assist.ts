import { useHttp } from '@inertiajs/react';
import type { FieldAssistResult } from '@/design-system/primitives';
import { assistantErrorMessage } from '@/lib/assistant-error';
import { useTranslation } from '@/i18n';
import { textRewrite } from '@/routes/admin/assistant';

type Mode = App.Enums.TextRewriteMode;
type Suggestion = App.Data.Admin.Assistant.TextRewriteSuggestionData;

type TextRewriteInput = {
    subject: 'article' | 'page';
    mode: Mode;
    text: string;
    max_length: number | null;
};

/** Mirrors `RewriteTextRequest::TEXT_MAX`; longer text is not sent. */
const TEXT_REWRITE_MAX = 5_000;

/** Mirrors `RewriteTextRequest::MAX_LENGTH_MIN`. */
const MAX_LENGTH_MIN = 20;

/** Order of the modes offered to the user; mirrors `TextRewriteMode`. */
const TEXT_REWRITE_MODES: readonly Mode[] = [
    'fix',
    'shorten',
    'simplify',
    'tone_formal',
    'tone_casual',
];

/**
 * "Improve text" helper of one plain text field: translated labels, the
 * fixed modes and a request per mode. The result is only a proposal; the
 * field changes after the user picks "replace".
 */
export type TextRewriteAssist = {
    labels: {
        request: string;
        preview: string;
        apply: string;
        discard: string;
    };
    modes: { value: Mode; label: string }[];
    request: (mode: Mode) => Promise<FieldAssistResult>;
};

export type TextRewriteSource = {
    /** Current value of the field. */
    text: string;
    /** Limit of the field (e.g. the column length); the result is cut to it. */
    maxLength?: number;
};

/**
 * Builds the "improve text" helper of plain text fields of a page or
 * article. Returns `undefined` when the assistant is not available to the
 * user (`can.rewriteText`).
 */
export function useTextRewriteAssist({
    enabled,
    subject,
}: {
    enabled: boolean;
    subject: TextRewriteInput['subject'];
}): ((source: TextRewriteSource) => TextRewriteAssist) | undefined {
    const { t } = useTranslation();
    const http = useHttp<TextRewriteInput, Suggestion>({
        subject,
        mode: 'fix',
        text: '',
        max_length: null,
    });

    if (!enabled) {
        return undefined;
    }

    const labels = {
        request: t('admin.assistant.rewrite.request'),
        preview: t('admin.assistant.rewrite.preview'),
        apply: t('admin.assistant.rewrite.apply'),
        discard: t('admin.assistant.rewrite.discard'),
    };
    const modes = TEXT_REWRITE_MODES.map((value) => ({
        value,
        label: t(`admin.assistant.rewrite.modes.${value}`),
    }));

    return ({ text, maxLength }) => ({
        labels,
        modes,
        request: async (mode) => {
            const trimmed = text.trim();

            if (trimmed === '') {
                return { error: t('admin.assistant.rewrite.missingText') };
            }

            if (trimmed.length > TEXT_REWRITE_MAX) {
                return {
                    error: t('admin.assistant.rewrite.tooLong', {
                        max: TEXT_REWRITE_MAX,
                    }),
                };
            }

            const input: TextRewriteInput = {
                subject,
                mode,
                text: trimmed,
                max_length:
                    maxLength === undefined
                        ? null
                        : Math.min(
                              Math.max(maxLength, MAX_LENGTH_MIN),
                              TEXT_REWRITE_MAX,
                          ),
            };

            http.transform(() => input);

            try {
                const suggestion = await http.post(textRewrite.url());

                return { suggestion: suggestion.text };
            } catch (error) {
                return { error: assistantErrorMessage(error, t) };
            }
        },
    });
}
