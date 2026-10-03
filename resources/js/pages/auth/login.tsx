import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PasskeyVerify from '@/components/passkey-verify';
import {
    Alert,
    AuthHeading,
    Button,
    CheckboxField,
    Link,
    PasswordField,
    Stack,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import {
    getLocalizedForgotPasswordRoute,
    getLocalizedLoginForm,
    getLocalizedRegisterRoute,
} from '@/lib/localized-routes';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const FIELD_ORDER = ['email', 'password'] as const;

export default function Login({ status, canResetPassword }: Props) {
    const canRegister =
        usePage<{ auth?: { canRegister?: boolean } }>().props.auth
            ?.canRegister ?? false;
    const { t, locale, defaultLocale } = useTranslation();
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const { method, action } = getLocalizedLoginForm(locale, defaultLocale);

        form.submit(method, action, {
            onError: (errors) => focusFirstError(errors, FIELD_ORDER),
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title={t('auth.login.title')} />

            <AuthHeading
                title={t('auth.login.heading')}
                description={t('auth.login.subheading')}
            />

            <Stack gap="default">
                {status && <Alert tone="success" title={status} />}

                <PasskeyVerify />

                <form onSubmit={submit} noValidate>
                    <Stack gap="default">
                        <TextField
                            id="email"
                            name="email"
                            type="email"
                            label={t('auth.login.email')}
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                            autoComplete="email"
                            placeholder={t('auth.login.emailPlaceholder')}
                            required
                        />

                        <PasswordField
                            id="password"
                            name="password"
                            label={t('auth.login.password')}
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

                        {canResetPassword && (
                            <Text variant="caption" align="end">
                                <Link
                                    href={getLocalizedForgotPasswordRoute(
                                        locale,
                                        defaultLocale,
                                    )}
                                    tone="muted"
                                >
                                    {t('auth.login.forgotPassword')}
                                </Link>
                            </Text>
                        )}

                        <CheckboxField
                            name="remember"
                            label={t('auth.login.remember')}
                            checked={form.data.remember}
                            onChange={(checked) =>
                                form.setData('remember', checked)
                            }
                        />

                        <Button type="submit" isPending={form.processing}>
                            {t('auth.login.submit')}
                        </Button>
                    </Stack>
                </form>

                {canRegister && (
                    <Text variant="caption" align="center">
                        {t('auth.login.noAccount')}{' '}
                        <Link
                            href={getLocalizedRegisterRoute(
                                locale,
                                defaultLocale,
                            )}
                        >
                            {t('auth.login.signUp')}
                        </Link>
                    </Text>
                )}
            </Stack>
        </>
    );
}
