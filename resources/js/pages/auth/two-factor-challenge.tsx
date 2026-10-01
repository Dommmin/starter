import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    AuthHeading,
    Button,
    OtpField,
    Stack,
    TextField,
} from '@/design-system/primitives';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslation } from '@/i18n';
import { focusFirstError } from '@/lib/focus-first-error';
import { getLocalizedTwoFactorLoginForm } from '@/lib/localized-routes';

const FIELD_ORDER = ['code', 'recovery_code'] as const;

export default function TwoFactorChallenge() {
    const { t, locale, defaultLocale } = useTranslation();
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');
    const [recoveryCode, setRecoveryCode] = useState<string>('');

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        const nextFieldId = showRecoveryInput ? 'code' : 'recovery_code';

        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
        setRecoveryCode('');
        // Keyboard users continue in the field that replaced the old one.
        window.setTimeout(() => document.getElementById(nextFieldId)?.focus());
    };

    return (
        <>
            <Head title={t('auth.twoFactor.title')} />

            {showRecoveryInput ? (
                <AuthHeading
                    title={t('auth.twoFactor.recoveryTitle')}
                    description={t('auth.twoFactor.recoveryDescription')}
                />
            ) : (
                <AuthHeading
                    title={t('auth.twoFactor.heading')}
                    description={t('auth.twoFactor.subheading')}
                />
            )}

            <Form
                {...getLocalizedTwoFactorLoginForm(locale, defaultLocale)}
                resetOnError
                resetOnSuccess={!showRecoveryInput}
                onError={(errors) => {
                    setCode('');
                    setRecoveryCode('');
                    // The OTP input is disabled while the request runs, so
                    // focus waits for the re-enabled control.
                    window.setTimeout(() =>
                        focusFirstError(errors, FIELD_ORDER),
                    );
                }}
            >
                {({ errors, processing, clearErrors }) => (
                    <Stack gap="default">
                        {showRecoveryInput ? (
                            <TextField
                                id="recovery_code"
                                name="recovery_code"
                                label={t('auth.twoFactor.recoveryCode')}
                                value={recoveryCode}
                                onChange={setRecoveryCode}
                                error={errors.recovery_code}
                                placeholder={t(
                                    'auth.twoFactor.recoveryPlaceholder',
                                )}
                                autoComplete="one-time-code"
                                required
                            />
                        ) : (
                            <Stack gap="none" align="center">
                                <OtpField
                                    id="code"
                                    name="code"
                                    label={t('auth.twoFactor.code')}
                                    length={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    error={errors.code}
                                    isPending={processing}
                                    required
                                    autoFocus
                                />
                            </Stack>
                        )}

                        <Button type="submit" isPending={processing}>
                            {t('auth.twoFactor.submit')}
                        </Button>

                        <Button
                            variant="link"
                            onClick={() => toggleRecoveryMode(clearErrors)}
                        >
                            {showRecoveryInput
                                ? t('auth.twoFactor.useAuthCode')
                                : t('auth.twoFactor.useRecoveryCode')}
                        </Button>
                    </Stack>
                )}
            </Form>
        </>
    );
}
