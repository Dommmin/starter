import {
    BrandLogo,
    CenteredLayout,
    Heading,
    Seo,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

/**
 * Shown (503) to guests instead of the public site while the maintenance
 * switch of the System page is on. The expected return is rounded up to
 * whole hours from one hour on.
 */
export default function Maintenance({
    contactEmail,
    minutesLeft,
}: App.Data.Content.MaintenancePageData) {
    const { t } = useTranslation();

    return (
        <>
            <Seo
                title={t('maintenance.title')}
                description={t('maintenance.description')}
                robots="noindex,nofollow"
            />

            <CenteredLayout>
                <Stack gap="relaxed" align="center">
                    <BrandLogo />
                    <Stack gap="tight" align="center">
                        <Heading level={1} variant="page" align="center">
                            {t('maintenance.heading')}
                        </Heading>
                        <Text tone="muted" align="center">
                            {t('maintenance.description')}
                        </Text>
                    </Stack>
                    {minutesLeft !== null && (
                        <Text align="center">
                            {minutesLeft < 60
                                ? t(
                                      'maintenance.returnsInMinutes',
                                      {},
                                      minutesLeft,
                                  )
                                : t(
                                      'maintenance.returnsInHours',
                                      {},
                                      Math.ceil(minutesLeft / 60),
                                  )}
                        </Text>
                    )}
                    {contactEmail && (
                        <Text align="center">
                            {t('maintenance.contact', { email: contactEmail })}
                        </Text>
                    )}
                </Stack>
            </CenteredLayout>
        </>
    );
}
