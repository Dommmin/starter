import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    Alert,
    AuthHeading,
    Button,
    Link,
    Stack,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import {
    getLocalizedForgotPasswordForm,
    getLocalizedLoginRoute,
} from '@/lib/localized-routes';

const FIELD_ORDER = ['email'] as const;

export default function ForgotPassword({ status }: { status?: string }) {
    const { t, locale, defaultLocale } = useTranslation();
    const form = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const { method, action } = getLocalizedForgotPasswordForm(
            locale,
            defaultLocale,
        );

        form.submit(method, action, {
            onError: (errors) => focusFirstError(errors, FIELD_ORDER),
        });
    }

    // Also on the <form>: a submit before hydration posts to the same
    // endpoint instead of a GET that would put the password in the URL.
    const formTarget = getLocalizedForgotPasswordForm(locale, defaultLocale);

    return (
        <>
            <Head title={t('auth.forgotPassword.title')} />

            <AuthHeading
                title={t('auth.forgotPassword.heading')}
                description={t('auth.forgotPassword.subheading')}
            />

            <Stack gap="default">
                {status && <Alert tone="success" title={status} />}

                <form
                    method={formTarget.method}
                    action={formTarget.action}
                    onSubmit={submit}
                    noValidate
                >
                    <Stack gap="default">
                        <TextField
                            id="email"
                            name="email"
                            type="email"
                            label={t('auth.forgotPassword.email')}
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                            autoComplete="email"
                            placeholder={t(
                                'auth.forgotPassword.emailPlaceholder',
                            )}
                            required
                        />

                        <Button type="submit" isPending={form.processing}>
                            {t('auth.forgotPassword.submit')}
                        </Button>
                    </Stack>
                </form>

                <Text variant="caption" align="center">
                    {t('auth.forgotPassword.returnTo')}{' '}
                    <Link href={getLocalizedLoginRoute(locale, defaultLocale)}>
                        {t('auth.forgotPassword.logIn')}
                    </Link>
                </Text>
            </Stack>
        </>
    );
}
