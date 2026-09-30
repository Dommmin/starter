import {
    Heading,
    PublicChrome,
    RichTextContent,
    Section,
    Seo,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function PageShow({
    title,
    metaDescription,
    bodyHtml,
    locale,
    publishedAt,
    alternates,
}: App.Data.Content.PublicPageData) {
    const { t, formatDate } = useTranslation();
    const canonical = alternates[locale];

    return (
        <>
            <Seo
                title={title}
                description={metaDescription}
                canonical={canonical}
                alternates={alternates}
                type="article"
                publishedAt={publishedAt}
                jsonLd={{
                    '@context': 'https://schema.org',
                    '@type': 'WebPage',
                    name: title,
                    ...(metaDescription
                        ? { description: metaDescription }
                        : {}),
                    ...(canonical ? { url: canonical } : {}),
                    inLanguage: locale,
                    ...(publishedAt ? { datePublished: publishedAt } : {}),
                }}
            />

            <PublicChrome>
                <Section spacing="default" container="reading">
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Heading level={1} variant="page">
                                {title}
                            </Heading>
                            {publishedAt ? (
                                <Text variant="caption" tone="muted">
                                    {t('page.publishedOn', {
                                        date: formatDate(publishedAt, {
                                            dateStyle: 'long',
                                        }),
                                    })}
                                </Text>
                            ) : null}
                        </Stack>
                        <RichTextContent html={bodyHtml} />
                    </Stack>
                </Section>
            </PublicChrome>
        </>
    );
}
