import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import {
    Alert,
    Badge,
    Button,
    ConfirmDialog,
    FormActions,
    Heading,
    Icon,
    Inline,
    PasswordField,
    SelectField,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { destroy, update } from '@/routes/admin/system/ai';

const PROVIDERS: App.Enums.AiProvider[] = ['anthropic', 'openai', 'gemini'];

type AiSettingsValues = {
    provider: App.Enums.AiProvider;
    api_key: string;
};

export type AiSettingsPanelProps = {
    ai: App.Data.Admin.System.AiSettingsData;
};

/**
 * AI provider and API key of the System page. The key is write-only: the
 * server never sends it back, the panel shows only where the key comes from
 * (panel, server configuration or none). Saving and removing ask for the
 * password (423 handled globally by PasswordConfirmationModal).
 */
export function AiSettingsPanel({ ai }: AiSettingsPanelProps) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [isRemoving, setIsRemoving] = useState(false);
    const form = useForm<AiSettingsValues>({
        provider: ai.provider ?? 'anthropic',
        api_key: '',
    });
    const keyRequired = !ai.hasPanelKey || form.data.provider !== ai.provider;
    const configured = ai.hasPanelKey || ai.environmentConfigured;
    const status = ai.hasPanelKey
        ? t('admin.ai.statusPanel')
        : ai.environmentConfigured
          ? t('admin.ai.statusEnvironment')
          : t('admin.ai.statusMissing');

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(update(), {
            preserveScroll: true,
            onSuccess: () => form.reset('api_key'),
        });
    }

    function remove() {
        router.delete(destroy.url(), {
            preserveScroll: true,
            onStart: () => setIsRemoving(true),
            onFinish: () => {
                setIsRemoving(false);
                setConfirmOpen(false);
            },
        });
    }

    return (
        <Surface tone="default" padding="default" radius="default" border>
            <Stack gap="default">
                <Stack gap="tight">
                    <Heading level={2} variant="group">
                        {t('admin.ai.title')}
                    </Heading>
                    <Text variant="caption" tone="muted">
                        {t('admin.ai.description')}
                    </Text>
                    <Inline gap="tight">
                        <Badge tone={configured ? 'success' : 'neutral'}>
                            <Inline gap="tight" align="center">
                                <Icon
                                    icon={
                                        configured
                                            ? CheckCircle2
                                            : AlertTriangle
                                    }
                                    size="sm"
                                />
                                {status}
                            </Inline>
                        </Badge>
                    </Inline>
                </Stack>

                {!ai.assistantEnabled && (
                    <Alert title={t('admin.ai.assistantDisabled')} />
                )}

                <form onSubmit={submit} noValidate>
                    <Stack gap="default">
                        <SelectField
                            name="provider"
                            label={t('admin.ai.provider')}
                            value={form.data.provider}
                            onChange={(value) => {
                                const provider = PROVIDERS.find(
                                    (item) => item === value,
                                );

                                if (provider) {
                                    form.setData('provider', provider);
                                }
                            }}
                            options={PROVIDERS.map((provider) => ({
                                value: provider,
                                label: t(`admin.ai.providers.${provider}`),
                            }))}
                            error={form.errors.provider}
                            required
                        />
                        <PasswordField
                            name="api_key"
                            label={t('admin.ai.apiKey')}
                            value={form.data.api_key}
                            onChange={(value) => form.setData('api_key', value)}
                            description={
                                keyRequired
                                    ? t('admin.ai.apiKeyRequiredHint')
                                    : t('admin.ai.apiKeyHint')
                            }
                            error={form.errors.api_key}
                            required={keyRequired}
                            autoComplete="off"
                            showPasswordLabel={t('auth.passwordField.show')}
                            hidePasswordLabel={t('auth.passwordField.hide')}
                        />
                        <FormActions align={ai.hasPanelKey ? 'between' : 'end'}>
                            {ai.hasPanelKey && (
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirmOpen(true)}
                                >
                                    {t('admin.ai.remove')}
                                </Button>
                            )}
                            <Button
                                type="submit"
                                isPending={form.processing}
                                disabled={form.processing}
                            >
                                {t('admin.ai.save')}
                            </Button>
                        </FormActions>
                    </Stack>
                </form>
            </Stack>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title={t('admin.ai.removeTitle')}
                description={t('admin.ai.removeDescription')}
                confirmLabel={t('admin.ai.removeConfirm')}
                cancelLabel={t('admin.ai.cancel')}
                closeLabel={t('admin.ai.close')}
                onConfirm={remove}
                tone="destructive"
                isPending={isRemoving}
            />
        </Surface>
    );
}
