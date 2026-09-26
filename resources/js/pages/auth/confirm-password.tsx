import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { getLocalizedPasswordConfirmForm } from '@/lib/localized-routes';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';
import { useTranslation } from '@/i18n';

export default function ConfirmPassword() {
    const { t, locale, defaultLocale } = useTranslation();

    setLayoutProps({
        title: t('auth.confirmPassword.heading'),
        description: t('auth.confirmPassword.subheading'),
    });

    return (
        <>
            <Head title={t('auth.confirmPassword.title')} />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label={t('auth.passkey.confirm')}
                loadingLabel={t('auth.passkey.confirming')}
                separator={t('auth.passkey.orPassword')}
            />

            <Form
                {...getLocalizedPasswordConfirmForm(locale, defaultLocale)}
                resetOnSuccess={['password']}
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth.confirmPassword.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder={t(
                                    'auth.confirmPassword.passwordPlaceholder',
                                )}
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.confirmPassword.submit')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}
