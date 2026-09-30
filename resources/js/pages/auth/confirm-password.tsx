import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';
import { Button, PasswordField, Stack } from '@/design-system/primitives';
import AuthPageHeading from '@/components/auth-page-heading';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import { getLocalizedPasswordConfirmForm } from '@/lib/localized-routes';

const FIELD_ORDER = ['password'] as const;

export default function ConfirmPassword() {
    const { t, locale, defaultLocale } = useTranslation();
    const form = useForm({ password: '' });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const { method, action } = getLocalizedPasswordConfirmForm(
            locale,
            defaultLocale,
        );

        form.submit(method, action, {
            onError: (errors) => focusFirstError(errors, FIELD_ORDER),
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title={t('auth.confirmPassword.title')} />

            <AuthPageHeading
                title={t('auth.confirmPassword.heading')}
                description={t('auth.confirmPassword.subheading')}
            />

            <Stack gap="default">
                <PasskeyVerify
                    routes={{
                        options: confirmOptions(),
                        submit: confirmStore(),
                    }}
                    label={t('auth.passkey.confirm')}
                    loadingLabel={t('auth.passkey.confirming')}
                    separator={t('auth.passkey.orPassword')}
                />

                <form onSubmit={submit} noValidate>
                    <Stack gap="default">
                        <PasswordField
                            id="password"
                            name="password"
                            label={t('auth.confirmPassword.password')}
                            value={form.data.password}
                            onChange={(value) =>
                                form.setData('password', value)
                            }
                            error={form.errors.password}
                            autoComplete="current-password"
                            showPasswordLabel={t('auth.passwordField.show')}
                            hidePasswordLabel={t('auth.passwordField.hide')}
                            required
                        />

                        <Button type="submit" isPending={form.processing}>
                            {t('auth.confirmPassword.submit')}
                        </Button>
                    </Stack>
                </form>
            </Stack>
        </>
    );
}
