import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button, Heading, Stack, Text } from '@/design-system/primitives';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { useTranslation } from '@/i18n';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const { t } = useTranslation();
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <Stack gap="default">
            <Stack gap="tight">
                <Heading level={2} variant="group">
                    {t('settings.security.twoFactor')}
                </Heading>
                <Text tone="muted">{t('settings.twoFactor.description')}</Text>
            </Stack>

            {twoFactorEnabled ? (
                <Stack gap="default" align="start">
                    <Text>{t('settings.twoFactor.enabledInfo')}</Text>

                    <Form {...disable.form()}>
                        {({ processing }) => (
                            <Button
                                variant="destructive"
                                type="submit"
                                isPending={processing}
                            >
                                {t('settings.twoFactor.disable')}
                            </Button>
                        )}
                    </Form>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </Stack>
            ) : (
                <Stack gap="default" align="start">
                    <Text>{t('settings.twoFactor.disabledInfo')}</Text>

                    {hasSetupData ? (
                        <Button onClick={() => setShowSetupModal(true)}>
                            <ShieldCheck />
                            {t('settings.twoFactor.continueSetup')}
                        </Button>
                    ) : (
                        <Form
                            {...enable.form()}
                            onSuccess={() => setShowSetupModal(true)}
                        >
                            {({ processing }) => (
                                <Button type="submit" isPending={processing}>
                                    {t('settings.twoFactor.enable')}
                                </Button>
                            )}
                        </Form>
                    )}
                </Stack>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </Stack>
    );
}
