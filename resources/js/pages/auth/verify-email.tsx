import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Alert, Button, Stack } from '@/design-system/primitives';
import AuthPageHeading from '@/components/auth-page-heading';
import { useTranslation } from '@/i18n';
import {
    getLocalizedLogoutRoute,
    getLocalizedVerificationSendForm,
} from '@/lib/localized-routes';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t, locale, defaultLocale } = useTranslation();
    const resendForm = useForm({});
    const logoutForm = useForm({});

    function resend(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (resendForm.processing) {
            return;
        }

        const { method, action } = getLocalizedVerificationSendForm(
            locale,
            defaultLocale,
        );

        resendForm.submit(method, action);
    }

    function logout(): void {
        if (logoutForm.processing) {
            return;
        }

        logoutForm.submit(getLocalizedLogoutRoute(locale, defaultLocale));
    }

    return (
        <>
            <Head title={t('auth.verifyEmail.title')} />

            <AuthPageHeading
                title={t('auth.verifyEmail.heading')}
                description={t('auth.verifyEmail.subheading')}
            />

            <Stack gap="default" align="center">
                {status === 'verification-link-sent' && (
                    <Alert
                        tone="success"
                        title={t('auth.verifyEmail.linkSent')}
                    />
                )}

                <form onSubmit={resend}>
                    <Button
                        type="submit"
                        variant="secondary"
                        isPending={resendForm.processing}
                    >
                        {t('auth.verifyEmail.resend')}
                    </Button>
                </form>

                <Button
                    variant="link"
                    onClick={logout}
                    isPending={logoutForm.processing}
                >
                    {t('auth.verifyEmail.logout')}
                </Button>
            </Stack>
        </>
    );
}
