import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import {
    Button,
    Heading,
    PasswordField,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import { describePasswordRules } from '@/lib/password-rules';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const FIELD_ORDER = [
    'current_password',
    'password',
    'password_confirmation',
] as const;

export default function Security(props: Props) {
    const { t } = useTranslation();
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const passwordLabels = {
        showPasswordLabel: t('auth.passwordField.show'),
        hidePasswordLabel: t('auth.passwordField.hide'),
    };

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit(SecurityController.update(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: (errors) => {
                form.reset();
                focusFirstError(errors, FIELD_ORDER);
            },
        });
    }

    return (
        <>
            <Head title={t('settings.security.pageTitle')} />

            <Stack gap="relaxed">
                <Stack gap="default">
                    <Stack gap="tight">
                        <Heading level={1} variant="group">
                            {t('settings.security.password')}
                        </Heading>
                        <Text tone="muted">
                            {t('settings.security.passwordDescription')}
                        </Text>
                    </Stack>

                    <form onSubmit={submit} noValidate>
                        <Stack gap="default">
                            <PasswordField
                                id="current_password"
                                name="current_password"
                                label={t('settings.security.currentPassword')}
                                value={form.data.current_password}
                                onChange={(value) =>
                                    form.setData('current_password', value)
                                }
                                error={form.errors.current_password}
                                autoComplete="current-password"
                                {...passwordLabels}
                                required
                            />

                            <PasswordField
                                id="password"
                                name="password"
                                label={t('settings.security.newPassword')}
                                value={form.data.password}
                                onChange={(value) =>
                                    form.setData('password', value)
                                }
                                error={form.errors.password}
                                description={describePasswordRules(
                                    props.passwordRules,
                                    t,
                                )}
                                autoComplete="new-password"
                                passwordRules={props.passwordRules}
                                {...passwordLabels}
                                required
                            />

                            <PasswordField
                                id="password_confirmation"
                                name="password_confirmation"
                                label={t('settings.security.confirmPassword')}
                                value={form.data.password_confirmation}
                                onChange={(value) =>
                                    form.setData('password_confirmation', value)
                                }
                                error={form.errors.password_confirmation}
                                autoComplete="new-password"
                                passwordRules={props.passwordRules}
                                {...passwordLabels}
                                required
                            />

                            <Stack gap="none" align="start">
                                <Button
                                    type="submit"
                                    isPending={form.processing}
                                >
                                    {t('settings.security.save')}
                                </Button>
                            </Stack>
                        </Stack>
                    </form>
                </Stack>

                <ManageTwoFactor
                    canManageTwoFactor={props.canManageTwoFactor}
                    requiresConfirmation={props.requiresConfirmation}
                    twoFactorEnabled={props.twoFactorEnabled}
                />

                <ManagePasskeys
                    canManagePasskeys={props.canManagePasskeys}
                    passkeys={props.passkeys}
                />
            </Stack>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
