import { Languages } from 'lucide-react';
import { useState } from 'react';
import {
    Alert,
    Button,
    ConfirmDialog,
    Inline,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export type TranslationAssistProps = {
    /** Native name of the default language the text is translated from. */
    sourceLanguage: string;
    /** Native name of the language of this tab. */
    targetLanguage: string;
    /** The source version has a title, so there is something to translate. */
    hasSource: boolean;
    /** This tab already has content that the translation would replace. */
    hasTargetContent: boolean;
    /** Requests the translation and fills the tab; resolves with an error message or `null`. */
    onTranslate: () => Promise<string | null>;
};

type Feedback = { tone: 'success' | 'danger'; message: string } | null;

/**
 * "Translate from <default language>" action of a non-default locale tab of
 * the page and article forms. The result fills the tab as a draft the admin
 * reads and saves; replacing existing content needs a confirmation.
 */
export function TranslationAssist({
    sourceLanguage,
    targetLanguage,
    hasSource,
    hasTargetContent,
    onTranslate,
}: TranslationAssistProps) {
    const { t } = useTranslation();
    const [isPending, setIsPending] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [feedback, setFeedback] = useState<Feedback>(null);

    async function translate() {
        setIsConfirmOpen(false);
        setIsPending(true);
        setFeedback(null);

        const error = await onTranslate();

        setIsPending(false);
        setFeedback(
            error === null
                ? {
                      tone: 'success',
                      message: t('admin.assistant.translation.done', {
                          language: sourceLanguage,
                      }),
                  }
                : { tone: 'danger', message: error },
        );
    }

    function start() {
        if (!hasSource) {
            setFeedback({
                tone: 'danger',
                message: t('admin.assistant.translation.missingSource', {
                    language: sourceLanguage,
                }),
            });

            return;
        }

        if (hasTargetContent) {
            setIsConfirmOpen(true);

            return;
        }

        void translate();
    }

    return (
        <Stack gap="tight">
            <Inline gap="tight" align="center" wrap>
                <Button
                    variant="outline"
                    size="sm"
                    isPending={isPending}
                    disabled={isPending}
                    onClick={start}
                >
                    <Languages aria-hidden="true" />
                    {isPending
                        ? t('admin.assistant.translation.pending')
                        : t('admin.assistant.translation.action', {
                              language: sourceLanguage,
                          })}
                </Button>
                <Text variant="caption" tone="muted">
                    {t('admin.assistant.translation.hint', {
                        language: sourceLanguage,
                    })}
                </Text>
            </Inline>

            {feedback && (
                <Alert
                    tone={feedback.tone}
                    title={feedback.message}
                    dismissLabel={t('actions.close')}
                    onDismiss={() => setFeedback(null)}
                />
            )}

            <ConfirmDialog
                open={isConfirmOpen}
                onOpenChange={setIsConfirmOpen}
                title={t('admin.assistant.translation.overwriteTitle')}
                description={t(
                    'admin.assistant.translation.overwriteDescription',
                    { language: targetLanguage, source: sourceLanguage },
                )}
                confirmLabel={t('admin.assistant.translation.overwriteConfirm')}
                cancelLabel={t('admin.assistant.translation.cancel')}
                closeLabel={t('actions.close')}
                onConfirm={() => void translate()}
            />
        </Stack>
    );
}
