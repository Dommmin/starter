import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    AuthHeading,
    Button,
    Link,
    PasswordField,
    Stack,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import {
    getLocalizedLoginRoute,
    getLocalizedRegisterForm,
} from '@/lib/localized-routes';
import { describePasswordRules } from '@/lib/password-rules';

type Props = {
    passwordRules: string;
};

const FIELD_ORDER = [
    'name',
    'email',
    'password',
    'password_confirmation',
] as const;

export default function Register({ passwordRules }: Props) {
    const { t, locale, defaultLocale } = useTranslation();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const { method, action } = getLocalizedRegisterForm(
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
            <Head title={t('auth.register.title')} />

            <AuthHeading
                title={t('auth.register.heading')}
                description={t('auth.register.subheading')}
            />

            <Stack gap="default">
                <form onSubmit={submit} noValidate>
                    <Stack gap="default">
                        <TextField
                            id="name"
                            name="name"
                            label={t('auth.register.name')}
                            value={form.data.name}
                            onChange={(value) => form.setData('name', value)}
                            error={form.errors.name}
                            autoComplete="name"
                            placeholder={t('auth.register.namePlaceholder')}
                            required
                        />

                        <TextField
                            id="email"
                            name="email"
                            type="email"
                            label={t('auth.register.email')}
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                            autoComplete="email"
                            placeholder={t('auth.register.emailPlaceholder')}
                            required
                        />

                        <PasswordField
                            id="password"
                            name="password"
                            label={t('auth.register.password')}
                            value={form.data.password}
                            onChange={(value) =>
                                form.setData('password', value)
                            }
                            error={form.errors.password}
                            description={describePasswordRules(
                                passwordRules,
                                t,
                            )}
                            autoComplete="new-password"
                            passwordRules={passwordRules}
                            {...passwordLabels}
                            required
                        />

                        <PasswordField
                            id="password_confirmation"
                            name="password_confirmation"
                            label={t('auth.register.passwordConfirmation')}
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
                            {t('auth.register.submit')}
                        </Button>
                    </Stack>
                </form>

                <Text variant="caption" align="center">
                    {t('auth.register.hasAccount')}{' '}
                    <Link href={getLocalizedLoginRoute(locale, defaultLocale)}>
                        {t('auth.register.logIn')}
                    </Link>
                </Text>
            </Stack>
        </>
    );
}
