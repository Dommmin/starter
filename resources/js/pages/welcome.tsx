import { usePage } from '@inertiajs/react';
import { Accessibility, ArrowRight, Lock, Palette, Zap } from 'lucide-react';
import { ContactForm } from '@/components/contact-form';
import {
    Button,
    CTA,
    FeatureGrid,
    Grid,
    Hero,
    PublicChrome,
    Section,
    Seo,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { login, register } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as articlesIndex } from '@/routes/articles';
import { index as localizedArticlesIndex } from '@/routes/localized/articles';

type WelcomeProps = App.Data.Content.WelcomePageData;

export default function Welcome() {
    const { t, locale, defaultLocale } = useTranslation();
    const page = usePage<WelcomeProps>();
    const { auth, seo, contactForm } = page.props;
    const alternateUrls =
        (page.props as { i18n?: { alternateUrls?: Record<string, string> } })
            .i18n?.alternateUrls ?? {};
    const canonicalUrl = alternateUrls[locale];

    const primaryActions = auth.user ? (
        <Button variant="primary" size="lg" href={adminIndex()}>
            <span>{t('landing.ctaPrimaryAuth')}</span>
            <ArrowRight aria-hidden="true" />
        </Button>
    ) : (
        <>
            <Button variant="primary" size="lg" href={register()}>
                <span>{t('landing.ctaPrimaryGuest')}</span>
                <ArrowRight aria-hidden="true" />
            </Button>
            <Button variant="outline" size="lg" href={login()}>
                <span>{t('nav.login')}</span>
            </Button>
        </>
    );

    return (
        <>
            <Seo
                canonical={canonicalUrl}
                alternates={alternateUrls}
                jsonLd={[
                    {
                        '@context': 'https://schema.org',
                        '@type': 'Organization',
                        name: seo.organization.name,
                        url: seo.organization.url,
                        ...(seo.organization.logo
                            ? { logo: seo.organization.logo }
                            : {}),
                    },
                    {
                        '@context': 'https://schema.org',
                        '@type': 'WebSite',
                        name: seo.siteName,
                        url: canonicalUrl ?? seo.canonical,
                        inLanguage: locale,
                        ...(seo.defaultDescription
                            ? { description: seo.defaultDescription }
                            : {}),
                    },
                ]}
            />

            <PublicChrome
                navItems={[
                    {
                        id: 'features',
                        kind: 'anchor',
                        label: t('nav.features'),
                        href: '#features',
                    },
                    {
                        id: 'articles',
                        kind: 'internal',
                        label: t('nav.articles'),
                        href: (locale === defaultLocale
                            ? articlesIndex()
                            : localizedArticlesIndex({ locale })
                        ).url,
                    },
                ]}
            >
                <Hero
                    eyebrow={t('landing.badge')}
                    title={t('landing.heroTitle')}
                    description={t('landing.heroDescription')}
                    actions={primaryActions}
                    align="center"
                />

                <Section spacing="compact" tone="default" container="wide">
                    <FeatureGrid
                        columns="cards"
                        items={[
                            {
                                id: 'architecture',
                                title: t('landing.statsArchitecture'),
                                description: t('landing.statsArchitectureDesc'),
                            },
                            {
                                id: 'quality',
                                title: t('landing.statsQuality'),
                                description: t('landing.statsQualityDesc'),
                            },
                            {
                                id: 'performance',
                                title: t('landing.statsPerformance'),
                                description: t('landing.statsPerformanceDesc'),
                            },
                        ]}
                    />
                </Section>

                {/* Sekcja funkcji */}
                <Section
                    spacing="relaxed"
                    tone="subtle"
                    container="wide"
                    id="features"
                    ariaLabelledBy="features-heading"
                >
                    <FeatureGrid
                        title={t('landing.featuresHeading')}
                        description={t('landing.featuresSubheading')}
                        columns="features"
                        items={[
                            {
                                id: 'ui-contract',
                                icon: Palette,
                                title: t('landing.feature1Title'),
                                description: t('landing.feature1Desc'),
                            },
                            {
                                id: 'security',
                                icon: Lock,
                                title: t('landing.feature2Title'),
                                description: t('landing.feature2Desc'),
                            },
                            {
                                id: 'ssr',
                                icon: Zap,
                                title: t('landing.feature3Title'),
                                description: t('landing.feature3Desc'),
                            },
                            {
                                id: 'a11y',
                                icon: Accessibility,
                                title: t('landing.feature4Title'),
                                description: t('landing.feature4Desc'),
                            },
                        ]}
                    />
                </Section>

                {/* Sekcja podglądu panelu i integracji */}
                <Section spacing="relaxed" tone="default" container="wide">
                    <Surface tone="subtle" padding="relaxed" radius="lg">
                        <Grid layout="split" gap="relaxed">
                            <Stack gap="default" align="start">
                                <Surface
                                    tone="raised"
                                    padding="compact"
                                    radius="full"
                                    border={false}
                                >
                                    <Text variant="label" tone="primary">
                                        {t('landing.demoBadge')}
                                    </Text>
                                </Surface>

                                <Text variant="lead" tone="default" as="p">
                                    {t('landing.demoTitle')}
                                </Text>

                                <Text variant="body" tone="muted">
                                    {t('landing.demoDescription')}
                                </Text>

                                <Text
                                    variant="body"
                                    tone="subtle"
                                    as="blockquote"
                                >
                                    {t('landing.demoQuote')}
                                </Text>
                            </Stack>

                            <Stack gap="default" align="stretch">
                                <Surface
                                    tone="raised"
                                    padding="default"
                                    radius="default"
                                >
                                    <Grid layout="split" gap="tight">
                                        <Text variant="label" tone="muted">
                                            {t('landing.envStatus')}
                                        </Text>
                                        <Text
                                            variant="label"
                                            tone="success"
                                            align="end"
                                        >
                                            {t('landing.envReady')}
                                        </Text>
                                    </Grid>
                                </Surface>

                                <Surface
                                    tone="raised"
                                    padding="default"
                                    radius="default"
                                >
                                    <Grid layout="split" gap="tight">
                                        <Text variant="label" tone="muted">
                                            {t('landing.stackLabel')}
                                        </Text>
                                        <Text
                                            variant="code"
                                            tone="default"
                                            align="end"
                                        >
                                            {t('landing.stackValue')}
                                        </Text>
                                    </Grid>
                                </Surface>
                            </Stack>
                        </Grid>
                    </Surface>
                </Section>

                <ContactForm contactForm={contactForm} />

                <CTA
                    title={t('landing.ctaBottomTitle')}
                    description={t('landing.ctaBottomDescription')}
                    tone="inverted"
                    actions={primaryActions}
                />
            </PublicChrome>
        </>
    );
}
