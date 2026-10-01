import { Form } from '@inertiajs/react';
import { Check, Copy, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    Button as DsButton,
    Inline,
    OtpField,
    Stack,
} from '@/design-system/primitives';
import { useAppearance } from '@/hooks/use-appearance';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslation } from '@/i18n';
import { confirm } from '@/routes/two-factor';

function GridScanIcon() {
    return (
        <div className="border-border bg-card mb-3 rounded-full border p-0.5 shadow-sm">
            <div className="border-border bg-muted relative overflow-hidden rounded-full border p-2.5">
                <div className="absolute inset-0 grid grid-cols-5 opacity-50">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={`col-${i + 1}`}
                            className="border-border border-r last:border-r-0"
                        />
                    ))}
                </div>
                <div className="absolute inset-0 grid grid-rows-5 opacity-50">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={`row-${i + 1}`}
                            className="border-border border-b last:border-b-0"
                        />
                    ))}
                </div>
                <ScanLine
                    className="text-foreground relative z-20 size-6"
                    aria-hidden="true"
                />
            </div>
        </div>
    );
}

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    buttonText,
    onNextStep,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    buttonText: string;
    onNextStep: () => void;
    errors: string[];
}) {
    const { t } = useTranslation();
    const { resolvedAppearance } = useAppearance();
    const [copiedText, copy] = useClipboard();
    const IconComponent = copiedText === manualSetupKey ? Check : Copy;

    return (
        <>
            {errors?.length ? (
                <AlertError
                    errors={errors}
                    title={t('settings.twoFactor.errorTitle')}
                />
            ) : (
                <>
                    <div className="mx-auto flex max-w-md overflow-hidden">
                        <div className="border-border mx-auto aspect-square w-64 rounded-lg border">
                            <div className="z-10 flex h-full w-full items-center justify-center p-5">
                                {qrCodeSvg ? (
                                    <div
                                        role="img"
                                        aria-label={t(
                                            'settings.twoFactor.qrCode',
                                        )}
                                        className="aspect-square w-full rounded-lg bg-white p-2 [&_svg]:size-full"
                                        dangerouslySetInnerHTML={{
                                            __html: qrCodeSvg,
                                        }}
                                        style={{
                                            filter:
                                                resolvedAppearance === 'dark'
                                                    ? 'invert(1) brightness(1.5)'
                                                    : undefined,
                                        }}
                                    />
                                ) : (
                                    <Spinner />
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex w-full space-x-5">
                        <Button className="w-full" onClick={onNextStep}>
                            {buttonText}
                        </Button>
                    </div>

                    <div className="relative flex w-full items-center justify-center">
                        <div className="bg-border absolute inset-0 top-1/2 h-px w-full" />
                        <span className="bg-card relative px-2 py-1">
                            {t('settings.twoFactor.orManual')}
                        </span>
                    </div>

                    <div className="flex w-full space-x-2">
                        <div className="border-border flex w-full items-stretch overflow-hidden rounded-xl border">
                            {!manualSetupKey ? (
                                <div className="bg-muted flex h-full w-full items-center justify-center p-3">
                                    <Spinner />
                                </div>
                            ) : (
                                <>
                                    <input
                                        type="text"
                                        readOnly
                                        value={manualSetupKey}
                                        aria-label={t(
                                            'settings.twoFactor.setupKey',
                                        )}
                                        className="bg-background text-foreground h-full w-full p-3 outline-none"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => copy(manualSetupKey)}
                                        aria-label={t(
                                            'settings.twoFactor.copySetupKey',
                                        )}
                                        className="border-border hover:bg-muted border-l px-3"
                                    >
                                        <IconComponent
                                            className="w-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </>
            )}
        </>
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
                        <DsButton
                            variant="outline"
                            onClick={onBack}
                            disabled={processing}
                        >
                            {t('settings.twoFactor.back')}
                        </DsButton>
                        <DsButton
                            type="submit"
                            isPending={processing}
                            disabled={code.length < OTP_MAX_LENGTH}
                        >
                            {t('settings.twoFactor.confirm')}
                        </DsButton>
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
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="flex items-center justify-center">
                    <GridScanIcon />
                    <DialogTitle>{modalConfig.title}</DialogTitle>
                    <DialogDescription className="text-center">
                        {modalConfig.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center space-y-5">
                    {showVerificationStep ? (
                        <TwoFactorVerificationStep
                            onClose={handleClose}
                            onBack={() => setShowVerificationStep(false)}
                        />
                    ) : (
                        <TwoFactorSetupStep
                            qrCodeSvg={qrCodeSvg}
                            manualSetupKey={manualSetupKey}
                            buttonText={modalConfig.buttonText}
                            onNextStep={handleModalNextStep}
                            errors={errors}
                        />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
