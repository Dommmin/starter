import { Form } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Alert,
    Button,
    Dialog,
    Inline,
    OtpField,
    QrCode,
    Spinner,
    Stack,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslation } from '@/i18n';
import { confirm } from '@/routes/two-factor';

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    errors: string[];
}) {
    const { t } = useTranslation();
    const [copiedText, copy] = useClipboard();
    const isCopied = manualSetupKey !== null && copiedText === manualSetupKey;
    const CopyIcon = isCopied ? Check : Copy;

    if (errors.length > 0) {
        return (
            <Alert
                tone="danger"
                title={t('settings.twoFactor.errorTitle')}
                description={Array.from(new Set(errors)).join(' ')}
            />
        );
    }

    return (
        <Stack gap="default">
            {qrCodeSvg ? (
                <QrCode
                    svg={qrCodeSvg}
                    label={t('settings.twoFactor.qrCode')}
                />
            ) : (
                <Inline justify="center">
                    <Spinner label={t('settings.twoFactor.loadingSetup')} />
                </Inline>
            )}

            <Text variant="caption" tone="muted" align="center">
                {t('settings.twoFactor.orManual')}
            </Text>

            {manualSetupKey ? (
                <Stack gap="tight">
                    <TextField
                        name="setup-key"
                        label={t('settings.twoFactor.setupKey')}
                        value={manualSetupKey}
                        readOnly
                    />
                    <Inline justify="end">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => void copy(manualSetupKey)}
                        >
                            <CopyIcon aria-hidden="true" />
                            {isCopied
                                ? t('settings.twoFactor.setupKeyCopied')
                                : t('settings.twoFactor.copySetupKey')}
                        </Button>
                    </Inline>
                </Stack>
            ) : (
                <Inline justify="center">
                    <Spinner label={t('settings.twoFactor.loadingSetup')} />
                </Inline>
            )}
        </Stack>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const { t } = useTranslation();
    const [code, setCode] = useState<string>('');
    const codeInput = useRef<HTMLInputElement>(null);

    return (
        <Form
            {...confirm.form()}
            onSuccess={() => onClose()}
            onError={() => {
                setCode('');
                // The field is disabled while the request runs, so focus
                // waits for the re-enabled control.
                window.setTimeout(() => codeInput.current?.focus());
            }}
            resetOnError
            resetOnSuccess
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors?: { confirmTwoFactorAuthentication?: { code?: string } };
            }) => (
                <Stack gap="default">
                    <OtpField
                        ref={codeInput}
                        id="otp"
                        name="code"
                        label={t('settings.twoFactor.code')}
                        length={OTP_MAX_LENGTH}
                        value={code}
                        onChange={setCode}
                        error={errors?.confirmTwoFactorAuthentication?.code}
                        isPending={processing}
                        required
                        autoFocus
                    />

                    <Inline gap="tight" justify="end" wrap>
                        <Button
                            variant="outline"
                            onClick={onBack}
                            disabled={processing}
                        >
                            {t('settings.twoFactor.back')}
                        </Button>
                        <Button
                            type="submit"
                            isPending={processing}
                            disabled={code.length < OTP_MAX_LENGTH}
                        >
                            {t('settings.twoFactor.confirm')}
                        </Button>
                    </Inline>
                </Stack>
            )}
        </Form>
    );
}

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const { t } = useTranslation();
    const [showVerificationStep, setShowVerificationStep] =
        useState<boolean>(false);

    const modalConfig = useMemo<{
        title: string;
        description: string;
        buttonText: string;
    }>(() => {
        if (twoFactorEnabled) {
            return {
                title: t('settings.twoFactor.enabledTitle'),
                description: t('settings.twoFactor.enabledDescription'),
                buttonText: t('settings.twoFactor.close'),
            };
        }

        if (showVerificationStep) {
            return {
                title: t('settings.twoFactor.verifyTitle'),
                description: t('settings.twoFactor.verifyDescription'),
                buttonText: t('settings.twoFactor.continue'),
            };
        }

        return {
            title: t('settings.twoFactor.setupTitle'),
            description: t('settings.twoFactor.setupDescription'),
            buttonText: t('settings.twoFactor.continue'),
        };
    }, [twoFactorEnabled, showVerificationStep, t]);

    const resetModalState = useCallback(() => {
        if (twoFactorEnabled) {
            clearSetupData();
        }

        setShowVerificationStep(false);
    }, [clearSetupData, twoFactorEnabled]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);

            return;
        }

        clearSetupData();
        handleClose();
    }, [requiresConfirmation, clearSetupData, handleClose]);

    const fetchSetupDataRef = useRef(fetchSetupData);

    useEffect(() => {
        fetchSetupDataRef.current = fetchSetupData;
    }, [fetchSetupData]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            void fetchSetupDataRef.current();
        }
    }, [isOpen, qrCodeSvg]);

    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => !open && handleClose()}
            title={modalConfig.title}
            description={modalConfig.description}
            closeLabel={t('settings.twoFactor.close')}
            actions={
                showVerificationStep || errors.length > 0 ? undefined : (
                    <Button onClick={handleModalNextStep}>
                        {modalConfig.buttonText}
                    </Button>
                )
            }
        >
            {showVerificationStep ? (
                <TwoFactorVerificationStep
                    onClose={handleClose}
                    onBack={() => setShowVerificationStep(false)}
                />
            ) : (
                <TwoFactorSetupStep
                    qrCodeSvg={qrCodeSvg}
                    manualSetupKey={manualSetupKey}
                    errors={errors}
                />
            )}
        </Dialog>
    );
}
