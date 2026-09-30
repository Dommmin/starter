import { ContentPreviewBar } from '@/components/content/content-preview-bar';
import {
    Heading,
    Image,
    Link,
    PublicChrome,
    RichTextContent,
    Section,
    Seo,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function ArticleShow({
    title,
    excerpt,
    metaDescription,
    bodyHtml,
    locale,
    publishedAt,
    updatedAt,
    cover,
    coverAlt,
    listUrl,
    alternates,
    preview,
}: App.Data.Content.PublicArticleData) {
    const { t, formatDate } = useTranslation();
    const canonical = alternates[locale];

    return (
        <>
            <Seo
                title={title}
                description={metaDescription}
                canonical={canonical}
                alternates={alternates}
                robots={preview ? 'noindex,nofollow' : undefined}
                image={cover?.src}
                type="article"
                publishedAt={publishedAt}
                jsonLd={{
                    '@context': 'https://schema.org',
                    '@type': 'Article',
                    headline: title,
                    ...(metaDescription
                        ? { description: metaDescription }
                        : {}),
                    ...(canonical ? { url: canonical } : {}),
                    ...(cover ? { image: cover.src } : {}),
                    inLanguage: locale,
                    ...(publishedAt ? { datePublished: publishedAt } : {}),
                    ...(updatedAt ? { dateModified: updatedAt } : {}),
                }}
            />

            <PublicChrome>
                {preview && <ContentPreviewBar preview={preview} />}
                <Section spacing="default" container="reading">
                    <article>
                        <Stack gap="default">
                            <Link href={listUrl}>
                                {t('articles.backToList')}
                            </Link>
                            <Stack gap="tight">
                                <Heading level={1} variant="page">
                                    {title}
                                </Heading>
                                {publishedAt && (
                                    <Text variant="caption" tone="muted">
                                        {t('articles.publishedOn', {
                                            date: formatDate(publishedAt, {
                                                dateStyle: 'long',
                                            }),
                                        })}
                                    </Text>
                                )}
                                {excerpt && (
                                    <Text variant="lead" tone="muted">
                                        {excerpt}
                                    </Text>
                                )}
                            </Stack>
                            {cover && (
                                <Image
                                    src={cover.src}
                                    srcset={cover.srcset}
                                    sources={cover.sources}
                                    width={cover.width}
                                    height={cover.height}
                                    alt={coverAlt}
                                    sizes="(min-width: 48rem) 48rem, 100vw"
                                    priority
                                />
                            )}
                            <RichTextContent html={bodyHtml} />
                        </Stack>
                    </article>
                </Section>
            </PublicChrome>
        </>
    );
}
