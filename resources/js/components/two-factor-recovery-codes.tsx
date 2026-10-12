import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    Alert,
    Button,
    Heading,
    Icon,
    Inline,
    Skeleton,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const { t } = useTranslation();
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            void fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    return (
        <Surface padding="compact" border>
            <Stack gap="default">
                <Stack gap="tight">
                    <Inline gap="tight" align="center">
                        <Icon icon={LockKeyhole} tone="muted" />
                        <Heading level={3} variant="group">
                            {t('settings.twoFactor.recoveryTitle')}
                        </Heading>
                    </Inline>
                    <Text tone="muted">
                        {t('settings.twoFactor.recoveryDescription')}
                    </Text>
                </Stack>

                <Inline gap="default" wrap>
                    <Button
                        onClick={toggleCodesVisibility}
                        aria-expanded={codesAreVisible}
                        aria-controls="recovery-codes-section"
                    >
                        {codesAreVisible ? <EyeOff /> : <Eye />}
                        {codesAreVisible
                            ? t('settings.twoFactor.hideCodes')
                            : t('settings.twoFactor.showCodes')}
                    </Button>

                    {canRegenerateCodes && (
                        <Form
                            {...regenerateRecoveryCodes.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={fetchRecoveryCodes}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="secondary"
                                    type="submit"
                                    isPending={processing}
                                    ariaLabel={t(
                                        'settings.twoFactor.regenerate',
                                    )}
                                >
                                    <RefreshCw />
                                    {t('settings.twoFactor.regenerate')}
                                </Button>
                            )}
                        </Form>
                    )}
                </Inline>

                <div id="recovery-codes-section" hidden={!codesAreVisible}>
                    {errors?.length ? (
                        <Alert
                            tone="danger"
                            title={t('settings.twoFactor.errorTitle')}
                            description={Array.from(new Set(errors)).join(' ')}
                        />
                    ) : (
                        <Stack gap="tight">
                            <div
                                ref={codesSectionRef}
                                role="list"
                                aria-label={t('settings.twoFactor.codesList')}
                            >
                                <Surface tone="subtle" padding="compact">
                                    {recoveryCodesList.length ? (
                                        <Stack gap="none">
                                            {recoveryCodesList.map((code) => (
                                                <div key={code} role="listitem">
                                                    <Text
                                                        as="span"
                                                        variant="code"
                                                    >
                                                        {code}
                                                    </Text>
                                                </div>
                                            ))}
                                        </Stack>
                                    ) : (
                                        <div
                                            aria-label={t(
                                                'settings.twoFactor.loadingCodes',
                                            )}
                                        >
                                            <Skeleton shape="text" lines={8} />
                                        </div>
                                    )}
                                </Surface>
                            </div>
                            <Text variant="caption">
                                {t('settings.twoFactor.recoveryHint')}
                            </Text>
                        </Stack>
                    )}
                </div>
            </Stack>
        </Surface>
    );
}
