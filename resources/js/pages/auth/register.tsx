import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    getLocalizedLoginRoute,
    getLocalizedRegisterForm,
} from '@/lib/localized-routes';
import AuthPageHeading from '@/components/auth-page-heading';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const { t, locale, defaultLocale } = useTranslation();

    return (
        <>
            <Head title={t('auth.register.title')} />

            <AuthPageHeading
                title={t('auth.register.heading')}
                description={t('auth.register.subheading')}
            />
            <Form
                {...getLocalizedRegisterForm(locale, defaultLocale)}
                resetOnSuccess={['password', 'password_confirmation']}
                onError={(errors) =>
                    focusFirstError(errors, [
                        'name',
                        'email',
                        'password',
                        'password_confirmation',
                    ])
                }
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('auth.register.name')}
                                </Label>
                                <Input
                                    id="name"
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.name ? 'name-error' : undefined
                                    }
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    name="name"
                                    placeholder={t(
                                        'auth.register.namePlaceholder',
                                    )}
                                />
                                <InputError
                                    id="name-error"
                                    role="alert"
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('auth.register.email')}
                                </Label>
                                <Input
                                    id="email"
                                    aria-invalid={
                                        errors.email ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.email ? 'email-error' : undefined
                                    }
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder={t(
                                        'auth.register.emailPlaceholder',
                                    )}
                                />
                                <InputError
                                    id="email-error"
                                    role="alert"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('auth.register.password')}
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
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={t(
                                        'auth.register.passwordPlaceholder',
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
                                    {t('auth.register.passwordConfirmation')}
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
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={t(
                                        'auth.register.passwordConfirmationPlaceholder',
                                    )}
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    id="password-confirmation-error"
                                    role="alert"
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.register.submit')}
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            {t('auth.register.hasAccount')}{' '}
                            <TextLink
                                href={getLocalizedLoginRoute(
                                    locale,
                                    defaultLocale,
                                )}
                            >
                                {t('auth.register.logIn')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
