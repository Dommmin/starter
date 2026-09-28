import { usePage } from '@inertiajs/react';
import { HomeSections } from '@/components/home/home-sections';
import { PublicChrome, Seo } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as articlesIndex } from '@/routes/articles';
import { index as localizedArticlesIndex } from '@/routes/localized/articles';

type WelcomeProps = App.Data.Content.WelcomePageData;

export default function Welcome() {
    const { t, locale, defaultLocale } = useTranslation();
    const page = usePage<WelcomeProps>();
    const { seo, contactForm, sections } = page.props;
    const alternateUrls =
        (page.props as { i18n?: { alternateUrls?: Record<string, string> } })
            .i18n?.alternateUrls ?? {};
    const canonicalUrl = alternateUrls[locale];

    return (
        <>
            <Seo
                title={t('landing.metaTitle')}
                description={t('landing.metaDescription')}
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
                        description: t('landing.metaDescription'),
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
                footer={{
                    copyright: `© ${new Date().getFullYear()} ${t('landing.footerCopy')}`,
                }}
            >
                <HomeSections
                    sections={sections}
                    contactForm={contactForm}
                    fallbackTitle={seo.siteName}
                />
            </PublicChrome>
        </>
    );
}
