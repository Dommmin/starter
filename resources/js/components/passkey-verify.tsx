import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import {
    Alert,
    Button,
    Separator,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    onSuccess?: () => void;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    onSuccess,
}: Props = {}) {
    const { t } = useTranslation();
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            if (onSuccess) {
                onSuccess();

                return;
            }

            router.visit(response.redirect ?? '/admin');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <Stack gap="default">
            <Stack gap="tight">
                <Button
                    type="button"
                    variant="outline"
                    onClick={verify}
                    isPending={isLoading}
                >
                    {!isLoading && <KeyRound />}
                    {isLoading
                        ? (loadingLabel ?? t('auth.passkey.authenticating'))
                        : (label ?? t('auth.passkey.signIn'))}
                </Button>
                {error && <Alert tone="danger" title={error} />}
            </Stack>

            <Stack gap="tight">
                <Separator />
                <Text variant="caption" align="center">
                    {separator ?? t('auth.passkey.orEmail')}
                </Text>
            </Stack>
        </Stack>
    );
}
