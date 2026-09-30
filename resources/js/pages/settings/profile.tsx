import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import {
    Alert,
    Button,
    Heading,
    Stack,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

const FIELD_ORDER = ['name', 'email'] as const;

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const form = useForm({ name: auth.user.name, email: auth.user.email });
    const verificationForm = useForm({});

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit(ProfileController.update(), {
            preserveScroll: true,
            onError: (errors) => focusFirstError(errors, FIELD_ORDER),
        });
    }

    function resendVerification(): void {
        if (verificationForm.processing) {
            return;
        }

        verificationForm.submit(send(), { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('settings.profile.pageTitle')} />

            <Stack gap="relaxed">
                <Stack gap="default">
                    <Stack gap="tight">
                        <Heading level={1} variant="group">
                            {t('settings.profile.heading')}
                        </Heading>
                        <Text tone="muted">
                            {t('settings.profile.subheading')}
                        </Text>
                    </Stack>

                    <form onSubmit={submit} noValidate>
                        <Stack gap="default">
                            <TextField
                                id="name"
                                name="name"
                                label={t('settings.profile.name')}
                                value={form.data.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                                error={form.errors.name}
                                autoComplete="name"
                                required
                            />

                            <TextField
                                id="email"
                                name="email"
                                type="email"
                                label={t('settings.profile.email')}
                                value={form.data.email}
                                onChange={(value) =>
                                    form.setData('email', value)
                                }
                                error={form.errors.email}
                                autoComplete="username"
                                required
                            />

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <Stack gap="tight" align="start">
                                        <Text variant="caption">
                                            {t('settings.profile.unverified')}
                                        </Text>
                                        <Button
                                            variant="link"
                                            onClick={resendVerification}
                                            isPending={
                                                verificationForm.processing
                                            }
                                        >
                                            {t(
                                                'settings.profile.resendVerification',
                                            )}
                                        </Button>
                                        {status ===
                                            'verification-link-sent' && (
                                            <Alert
                                                tone="success"
                                                title={t(
                                                    'settings.profile.verificationSent',
                                                )}
                                            />
                                        )}
                                    </Stack>
                                )}

                            <Stack gap="none" align="start">
                                <Button
                                    type="submit"
                                    isPending={form.processing}
                                >
                                    {t('settings.profile.save')}
                                </Button>
                            </Stack>
                        </Stack>
                    </form>
                </Stack>

                <DeleteUser />
            </Stack>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
