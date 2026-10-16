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
 * Shown to guests instead of the public site while the coming soon switch
 * of the System page is on. Always noindex (the site-wide flag is set too).
 */
export default function ComingSoon({
    contactEmail,
}: App.Data.Content.ComingSoonPageData) {
    const { t } = useTranslation();

    return (
        <>
            <Seo
                title={t('comingSoon.title')}
                description={t('comingSoon.description')}
                robots="noindex,nofollow"
            />

            <CenteredLayout>
                <Stack gap="relaxed" align="center">
                    <BrandLogo />
                    <Stack gap="tight" align="center">
                        <Heading level={1} variant="page" align="center">
                            {t('comingSoon.heading')}
                        </Heading>
                        <Text tone="muted" align="center">
                            {t('comingSoon.description')}
                        </Text>
                    </Stack>
                    {contactEmail && (
                        <Text align="center">
                            {t('comingSoon.contact', { email: contactEmail })}
                        </Text>
                    )}
                </Stack>
            </CenteredLayout>
        </>
    );
}
