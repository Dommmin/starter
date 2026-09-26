// Components
import { Form, Head, setLayoutProps } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    getLocalizedLogoutRoute,
    getLocalizedVerificationSendForm,
} from '@/lib/localized-routes';
import { useTranslation } from '@/i18n';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t, locale, defaultLocale } = useTranslation();

    setLayoutProps({
        title: t('auth.verifyEmail.heading'),
        description: t('auth.verifyEmail.subheading'),
    });

    return (
        <>
            <Head title={t('auth.verifyEmail.title')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('auth.verifyEmail.linkSent')}
                </div>
            )}

            <Form
                {...getLocalizedVerificationSendForm(locale, defaultLocale)}
                className="space-y-6 text-center"
            >
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('auth.verifyEmail.resend')}
                        </Button>

                        <TextLink
                            href={getLocalizedLogoutRoute(
                                locale,
                                defaultLocale,
                            )}
                            className="mx-auto block text-sm"
                        >
                            {t('auth.verifyEmail.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}
