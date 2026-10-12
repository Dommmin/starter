import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { Fragment } from 'react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import {
    EmptyState,
    Heading,
    Separator,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

export default function ManagePasskeys(props: Props) {
    const { t } = useTranslation();
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <Stack gap="default">
            <Stack gap="tight">
                <Heading level={2} variant="group">
                    {t('auth.passkey.manageTitle')}
                </Heading>
                <Text tone="muted">{t('auth.passkey.manageDescription')}</Text>
            </Stack>

            <Surface padding="compact" border>
                {passkeys.length > 0 ? (
                    <Stack gap="default">
                        {passkeys.map((passkey, index) => (
                            <Fragment key={passkey.id}>
                                {index > 0 && <Separator />}
                                <PasskeyItem
                                    passkey={passkey}
                                    onDelete={handleDelete}
                                />
                            </Fragment>
                        ))}
                    </Stack>
                ) : (
                    <EmptyState
                        icon={KeyRound}
                        title={t('auth.passkey.emptyTitle')}
                        description={t('auth.passkey.emptyDescription')}
                    />
                )}
            </Surface>

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </Stack>
    );
}
