import { router, usePage } from '@inertiajs/react';
import {
    Card,
    CardContent,
    EmptyState,
    Grid,
    Heading,
    Image,
    Link,
    Paginator,
    PublicChrome,
    Section,
    Seo,
    Stack,
    TagList,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function ArticlesIndex({
    items,
    pagination,
    locale,
    listUrl,
    tags,
    activeTag,
}: App.Data.Content.PublicArticleListData) {
    const { t, formatDate } = useTranslation();
    const alternateUrls =
        (
            usePage().props as {
                i18n?: { alternateUrls?: Record<string, string> };
            }
        ).i18n?.alternateUrls ?? {};
    const title = activeTag
        ? t('articles.taggedTitle', { tag: activeTag.name })
        : t('articles.title');

    function goToPage(page: number) {
        router.get(
            window.location.pathname,
            {
                ...(activeTag ? { tag: activeTag.slug } : {}),
                ...(page > 1 ? { page } : {}),
            },
            { preserveScroll: false },
        );
    }

    return (
        <>
            <Seo
                title={title}
                description={t('articles.description')}
                robots={
                    pagination.page > 1 || activeTag
                        ? 'noindex,follow'
                        : undefined
                }
                alternates={activeTag ? {} : alternateUrls}
                jsonLd={{
                    '@context': 'https://schema.org',
                    '@type': 'CollectionPage',
                    name: title,
                    description: t('articles.description'),
                    inLanguage: locale,
                }}
            />

            <PublicChrome>
                <Section spacing="default" container="wide">
                    <Stack gap="relaxed">
                        <Stack gap="tight">
                            <Heading level={1} variant="page">
                                {title}
                            </Heading>
                            <Text variant="lead" tone="muted">
                                {t('articles.description')}
                            </Text>
                        </Stack>

                        <TagList
                            label={t('articles.filterLabel')}
                            variant="filter"
                            items={
                                tags.length === 0
                                    ? []
                                    : [
                                          {
                                              id: '',
                                              label: t('articles.allTags'),
                                              href: listUrl,
                                              current: activeTag === null,
                                          },
                                          ...tags.map((tag) => ({
                                              id: tag.slug,
                                              label: tag.name,
                                              href: tag.url,
                                              current:
                                                  tag.slug === activeTag?.slug,
                                          })),
                                      ]
                            }
                        />

                        {items.length === 0 ? (
                            <EmptyState
                                title={
                                    activeTag
                                        ? t('articles.emptyTagged')
                                        : t('articles.empty')
                                }
                            />
                        ) : (
                            <Grid layout="cards">
                                {items.map((article) => (
                                    <Card key={article.url}>
                                        <CardContent>
                                            <Stack gap="tight">
                                                {article.cover && (
                                                    <Image
                                                        src={article.cover.src}
                                                        srcset={
                                                            article.cover.srcset
                                                        }
                                                        sources={
                                                            article.cover
                                                                .sources
                                                        }
                                                        width={
                                                            article.cover.width
                                                        }
                                                        height={
                                                            article.cover.height
                                                        }
                                                        alt=""
                                                        sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 45vw, 100vw"
                                                        frame="wide"
                                                    />
                                                )}
                                                <Heading
                                                    level={2}
                                                    variant="group"
                                                >
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
                                                        {t(
                                                            'articles.publishedOn',
                                                            {
                                                                date: formatDate(
                                                                    article.publishedAt,
                                                                    {
                                                                        dateStyle:
                                                                            'long',
                                                                    },
                                                                ),
                                                            },
                                                        )}
                                                    </Text>
                                                )}
                                                {article.excerpt && (
                                                    <Text tone="muted">
                                                        {article.excerpt}
                                                    </Text>
                                                )}
                                                <TagList
                                                    label={t(
                                                        'articles.tagsLabel',
                                                    )}
                                                    items={article.tags.map(
                                                        (tag) => ({
                                                            id: tag.slug,
                                                            label: tag.name,
                                                            href: tag.url,
                                                        }),
                                                    )}
                                                />
                                            </Stack>
                                        </CardContent>
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
            </PublicChrome>
        </>
    );
}
