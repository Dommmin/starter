import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import {
    Button,
    CheckboxGroupField,
    FormDialog,
    SelectField,
    Stack,
    TextareaField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { assistantErrorMessage } from '@/lib/assistant-error';
import { faqs as generateFaqs } from '@/routes/admin/assistant';
import { storeMany } from '@/routes/admin/faqs';

type Proposal = App.Data.Admin.Assistant.FaqSuggestionItemData;
type Suggestion = App.Data.Admin.Assistant.FaqSuggestionData;

type GenerateInput = { locale: string; text: string };

/** Mirrors `GenerateFaqsRequest::TEXT_MAX`. */
const TEXT_MAX = 20_000;

export type FaqGeneratorDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locales: App.Data.Content.ContentLocalesData;
};

/**
 * Two steps in one dialog: the admin pastes a source text, the assistant
 * proposes FAQ entries, then only the ticked entries are saved (unpublished)
 * through `admin.faqs.store-many`. Nothing is stored before "save selected".
 */
export function FaqGeneratorDialog({
    open,
    onOpenChange,
    locales,
}: FaqGeneratorDialogProps) {
    const { t } = useTranslation();
    const http = useHttp<GenerateInput, Suggestion>({
        locale: locales.default,
        text: '',
    });
    const [proposals, setProposals] = useState<Proposal[] | null>(null);
    const [selected, setSelected] = useState<string[]>([]);
    const [textError, setTextError] = useState<string | undefined>();
    const [error, setError] = useState<string | undefined>();
    const [isSaving, setIsSaving] = useState(false);

    function reset() {
        http.reset();
        http.clearErrors();
        setProposals(null);
        setSelected([]);
        setTextError(undefined);
        setError(undefined);
    }

    function close(nextOpen: boolean) {
        if (!nextOpen) {
            reset();
        }

        onOpenChange(nextOpen);
    }

    async function generate() {
        setError(undefined);
        setTextError(undefined);

        if (http.data.text.trim() === '') {
            setTextError(t('admin.faqs.generator.missingText'));

            return;
        }

        try {
            const response = await http.post(generateFaqs.url());

            // A validation error (422) resolves without a response; the
            // messages are in `http.errors`.
            if (response === undefined) {
                return;
            }

            if (response.items.length === 0) {
                setError(t('admin.faqs.generator.noProposals'));

                return;
            }

            setProposals(response.items);
            setSelected([]);
        } catch (requestError) {
            setError(assistantErrorMessage(requestError, t));
        }
    }

    function save() {
        if (proposals === null) {
            return;
        }

        if (selected.length === 0) {
            setError(t('admin.faqs.generator.noneSelected'));

            return;
        }

        setError(undefined);
        setIsSaving(true);
        router.post(
            storeMany.url(),
            {
                locale: http.data.locale,
                items: selected.map((index) => proposals[Number(index)]),
            },
            {
                preserveScroll: true,
                onSuccess: () => close(false),
                onError: () => setError(t('admin.faqs.generator.saveFailed')),
                onFinish: () => setIsSaving(false),
            },
        );
    }

    const isReviewing = proposals !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={close}
            title={t('admin.faqs.generator.title')}
            description={t('admin.faqs.generator.description')}
            submitLabel={
                isReviewing
                    ? t('admin.faqs.generator.save')
                    : t('admin.faqs.generator.generate')
            }
            cancelLabel={t('admin.faqs.cancel')}
            closeLabel={t('actions.close')}
            onSubmit={isReviewing ? save : generate}
            isPending={http.processing || isSaving}
            error={error}
        >
            {isReviewing ? (
                <Stack gap="tight">
                    <CheckboxGroupField
                        name="items"
                        label={t('admin.faqs.generator.proposals')}
                        description={t(
                            'admin.faqs.generator.proposalsDescription',
                        )}
                        value={selected}
                        onChange={(value) => {
                            setSelected(value);
                            setError(undefined);
                        }}
                        options={proposals.map((proposal, index) => ({
                            value: String(index),
                            label: proposal.question,
                            description: proposal.answer,
                        }))}
                        disabled={isSaving}
                    />
                    <Stack align="start">
                        <Button
                            variant="link"
                            disabled={isSaving}
                            onClick={() => {
                                setProposals(null);
                                setSelected([]);
                                setError(undefined);
                            }}
                        >
                            {t('admin.faqs.generator.back')}
                        </Button>
                    </Stack>
                </Stack>
            ) : (
                <Stack gap="tight">
                    <SelectField
                        name="locale"
                        label={t('admin.faqs.generator.locale')}
                        value={http.data.locale}
                        onChange={(value) => http.setData('locale', value)}
                        options={locales.available.map((locale) => ({
                            value: locale.code,
                            label: locale.native,
                        }))}
                        error={http.errors.locale}
                        required
                        disabled={http.processing}
                    />
                    <TextareaField
                        name="text"
                        label={t('admin.faqs.generator.text')}
                        description={t('admin.faqs.generator.textDescription')}
                        value={http.data.text}
                        onChange={(value) => {
                            http.setData('text', value.slice(0, TEXT_MAX));
                            setTextError(undefined);
                        }}
                        error={textError ?? http.errors.text}
                        rows={8}
                        required
                        disabled={http.processing}
                    />
                </Stack>
            )}
        </FormDialog>
    );
}
