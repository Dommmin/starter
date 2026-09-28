import { router } from '@inertiajs/react';
import {
    Card,
    EmptyState,
    Footer,
    Grid,
    Heading,
    Image,
    Link,
    Paginator,
    PublicHeader,
    Section,
    Seo,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function ArticlesIndex({
    items,
    pagination,
    locale,
}: App.Data.Content.PublicArticleListData) {
    const { t, formatDate } = useTranslation();

    function goToPage(page: number) {
        router.get(window.location.pathname, page > 1 ? { page } : {}, {
            preserveScroll: false,
        });
    }

    return (
        <>
            <Seo
                title={t('articles.title')}
                description={t('articles.description')}
                robots={pagination.page > 1 ? 'noindex,follow' : undefined}
                jsonLd={{
                    '@context': 'https://schema.org',
                    '@type': 'CollectionPage',
                    name: t('articles.title'),
                    description: t('articles.description'),
                    inLanguage: locale,
                }}
            />

            <PublicHeader />

            <main id="main-content">
                <Section spacing="default" container="wide">
                    <Stack gap="relaxed">
                        <Stack gap="tight">
                            <Heading level={1} variant="page">
                                {t('articles.title')}
                            </Heading>
                            <Text variant="lead" tone="muted">
                                {t('articles.description')}
                            </Text>
                        </Stack>

                        {items.length === 0 ? (
                            <EmptyState title={t('articles.empty')} />
                        ) : (
                            <Grid layout="cards">
                                {items.map((article) => (
                                    <Card key={article.url}>
                                        <Stack gap="tight">
                                            {article.cover && (
                                                <Image
                                                    src={article.cover.src}
                                                    srcset={
                                                        article.cover.srcset
                                                    }
                                                    sources={
                                                        article.cover.sources
                                                    }
                                                    width={article.cover.width}
                                                    height={
                                                        article.cover.height
                                                    }
                                                    alt=""
                                                    sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 45vw, 100vw"
                                                />
                                            )}
                                            <Heading level={2} variant="group">
                                                <Link
                                                    href={article.url}
                                                    ariaLabel={t(
                                                        'articles.readMore',
                                                        {
                                                            title: article.title,
                                                        },
                                                    )}
                                                >
                                                    {article.title}
                                                </Link>
                                            </Heading>
                                            {article.publishedAt && (
                                                <Text
                                                    variant="caption"
                                                    tone="muted"
                                                >
                                                    {t('articles.publishedOn', {
                                                        date: formatDate(
                                                            article.publishedAt,
                                                            {
                                                                dateStyle:
                                                                    'long',
                                                            },
                                                        ),
                                                    })}
                                                </Text>
                                            )}
                                            {article.excerpt && (
                                                <Text tone="muted">
                                                    {article.excerpt}
                                                </Text>
                                            )}
                                        </Stack>
                                    </Card>
                                ))}
                            </Grid>
                        )}

                        {pagination.totalPages > 1 && (
                            <Paginator
                                page={pagination.page}
                                totalPages={pagination.totalPages}
                                onPageChange={goToPage}
                                previousLabel={t('articles.previousPage')}
                                nextLabel={t('articles.nextPage')}
                                summary={t('articles.paginationSummary', {
                                    page: pagination.page,
                                    total: pagination.totalPages,
                                })}
                            />
                        )}
                    </Stack>
                </Section>
            </main>

            <Footer
                copyright={`© ${new Date().getFullYear()} ${t('landing.footerCopy')}`}
            />
        </>
    );
}
