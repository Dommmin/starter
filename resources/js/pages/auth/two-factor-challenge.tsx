import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Button, Stack, TextField } from '@/design-system/primitives';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import AuthPageHeading from '@/components/auth-page-heading';
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
                <AuthPageHeading
                    title={t('auth.twoFactor.recoveryTitle')}
                    description={t('auth.twoFactor.recoveryDescription')}
                />
            ) : (
                <AuthPageHeading
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
                            <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                <Label htmlFor="code">
                                    {t('auth.twoFactor.code')}
                                </Label>
                                <div className="flex w-full items-center justify-center">
                                    <InputOTP
                                        id="code"
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoComplete="one-time-code"
                                        aria-invalid={
                                            errors.code ? true : undefined
                                        }
                                        aria-describedby={
                                            errors.code
                                                ? 'code-error'
                                                : undefined
                                        }
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError
                                    id="code-error"
                                    role="alert"
                                    message={errors.code}
                                />
                            </div>
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
