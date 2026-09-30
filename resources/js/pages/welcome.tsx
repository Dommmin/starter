import { usePage } from '@inertiajs/react';
import { HomeSections } from '@/components/home/home-sections';
import { PublicChrome, Seo } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type WelcomeProps = App.Data.Content.WelcomePageData;

export default function Welcome() {
    const { locale } = useTranslation();
    const page = usePage<WelcomeProps>();
    const { seo, contactForm, sections } = page.props;
    const alternateUrls =
        (page.props as { i18n?: { alternateUrls?: Record<string, string> } })
            .i18n?.alternateUrls ?? {};
    const canonicalUrl = alternateUrls[locale];

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

            <PublicChrome>
                <HomeSections
                    sections={sections}
                    contactForm={contactForm}
                    fallbackTitle={seo.siteName}
                />
            </PublicChrome>
        </>
    );
}
