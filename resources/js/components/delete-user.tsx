import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import {
    Alert,
    Button,
    FormDialog,
    Heading,
    PasswordField,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function DeleteUser() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);
    const form = useForm({ password: '' });

    function changeOpen(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (!nextOpen) {
            form.reset();
            form.clearErrors();
        }
    }

    function submit(): void {
        form.delete(ProfileController.destroy.url(), {
            preserveScroll: true,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <Stack gap="default">
            <Stack gap="tight">
                <Heading level={2} variant="group">
                    {t('settings.deleteAccount.title')}
                </Heading>
                <Text tone="muted">
                    {t('settings.deleteAccount.description')}
                </Text>
            </Stack>

            <Alert
                tone="danger"
                title={t('settings.deleteAccount.warningTitle')}
                description={t('settings.deleteAccount.warningDescription')}
            />

            <Button variant="destructive" onClick={() => changeOpen(true)}>
                {t('settings.deleteAccount.submit')}
            </Button>

            <FormDialog
                open={open}
                onOpenChange={changeOpen}
                tone="destructive"
                title={t('settings.deleteAccount.confirmTitle')}
                description={t('settings.deleteAccount.confirmDescription')}
                submitLabel={t('settings.deleteAccount.submit')}
                cancelLabel={t('settings.deleteAccount.cancel')}
                closeLabel={t('settings.deleteAccount.close')}
                onSubmit={submit}
                isPending={form.processing}
            >
                <PasswordField
                    ref={passwordInput}
                    id="delete-user-password"
                    name="password"
                    label={t('settings.deleteAccount.password')}
                    value={form.data.password}
                    onChange={(value) => form.setData('password', value)}
                    error={form.errors.password}
                    autoComplete="current-password"
                    showPasswordLabel={t('auth.passwordField.show')}
                    hidePasswordLabel={t('auth.passwordField.hide')}
                    required
                />
            </FormDialog>
        </Stack>
    );
}
