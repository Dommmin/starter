import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    AuthHeading,
    Button,
    PasswordField,
    Stack,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import { getLocalizedResetPasswordForm } from '@/lib/localized-routes';
import { describePasswordRules } from '@/lib/password-rules';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

const FIELD_ORDER = ['email', 'password', 'password_confirmation'] as const;

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const { t, locale, defaultLocale } = useTranslation();
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const { method, action } = getLocalizedResetPasswordForm(
            locale,
            defaultLocale,
        );

        form.submit(method, action, {
            onError: (errors) => focusFirstError(errors, FIELD_ORDER),
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    const passwordLabels = {
        showPasswordLabel: t('auth.passwordField.show'),
        hidePasswordLabel: t('auth.passwordField.hide'),
    };

    return (
        <>
            <Head title={t('auth.resetPassword.title')} />

            <AuthHeading
                title={t('auth.resetPassword.heading')}
                description={t('auth.resetPassword.subheading')}
            />

            <form onSubmit={submit} noValidate>
                <Stack gap="default">
                    <TextField
                        id="email"
                        name="email"
                        type="email"
                        label={t('auth.resetPassword.email')}
                        value={form.data.email}
                        error={form.errors.email}
                        autoComplete="email"
                        readOnly
                    />

                    <PasswordField
                        id="password"
                        name="password"
                        label={t('auth.resetPassword.password')}
                        value={form.data.password}
                        onChange={(value) => form.setData('password', value)}
                        error={form.errors.password}
                        description={describePasswordRules(passwordRules, t)}
                        autoComplete="new-password"
                        passwordRules={passwordRules}
                        {...passwordLabels}
                        required
                    />

                    <PasswordField
                        id="password_confirmation"
                        name="password_confirmation"
                        label={t('auth.resetPassword.passwordConfirmation')}
                        value={form.data.password_confirmation}
                        onChange={(value) =>
                            form.setData('password_confirmation', value)
                        }
                        error={form.errors.password_confirmation}
                        autoComplete="new-password"
                        passwordRules={passwordRules}
                        {...passwordLabels}
                        required
                    />

                    <Button type="submit" isPending={form.processing}>
                        {t('auth.resetPassword.submit')}
                    </Button>
                </Stack>
            </form>
        </>
    );
}
