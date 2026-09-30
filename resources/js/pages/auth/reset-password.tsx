import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { getLocalizedResetPasswordForm } from '@/lib/localized-routes';
import AuthPageHeading from '@/components/auth-page-heading';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const { t, locale, defaultLocale } = useTranslation();

    return (
        <>
            <Head title={t('auth.resetPassword.title')} />

            <AuthPageHeading
                title={t('auth.resetPassword.heading')}
                description={t('auth.resetPassword.subheading')}
            />

            <Form
                {...getLocalizedResetPasswordForm(locale, defaultLocale)}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                onError={(errors) =>
                    focusFirstError(errors, [
                        'email',
                        'password',
                        'password_confirmation',
                    ])
                }
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">
                                {t('auth.resetPassword.email')}
                            </Label>
                            <Input
                                id="email"
                                aria-invalid={errors.email ? true : undefined}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                className="mt-1 block w-full"
                                readOnly
                            />
                            <InputError
                                id="email-error"
                                role="alert"
                                message={errors.email}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth.resetPassword.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                aria-invalid={
                                    errors.password ? true : undefined
                                }
                                aria-describedby={
                                    errors.password
                                        ? 'password-error'
                                        : undefined
                                }
                                name="password"
                                autoComplete="new-password"
                                className="mt-1 block w-full"
                                autoFocus
                                placeholder={t(
                                    'auth.resetPassword.passwordPlaceholder',
                                )}
                                passwordrules={passwordRules}
                            />
                            <InputError
                                id="password-error"
                                role="alert"
                                message={errors.password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('auth.resetPassword.passwordConfirmation')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                aria-invalid={
                                    errors.password_confirmation
                                        ? true
                                        : undefined
                                }
                                aria-describedby={
                                    errors.password_confirmation
                                        ? 'password-confirmation-error'
                                        : undefined
                                }
                                name="password_confirmation"
                                autoComplete="new-password"
                                className="mt-1 block w-full"
                                placeholder={t(
                                    'auth.resetPassword.passwordConfirmationPlaceholder',
                                )}
                                passwordrules={passwordRules}
                            />
                            <InputError
                                id="password-confirmation-error"
                                role="alert"
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {t('auth.resetPassword.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}
