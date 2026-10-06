<?php

namespace App\Actions\Seo;

use App\Contracts\Seo\SitemapSource;
use App\Models\ArticleTranslation;
use App\Models\PageTranslation;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizedUrlGenerator;
use DateTimeInterface;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Collection;

/**
 * Build the XML sitemap of canonical, indexable public URLs: the home page
 * and the article list in every active public locale, and every visible
 * page and article translation, with reciprocal hreflang alternates between
 * language versions, followed by the entries of the registered
 * SitemapSource modules (App\Providers\SitemapServiceProvider).
 */
class BuildSitemap
{
    /**
     * @param  iterable<SitemapSource>  $sources
     */
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly LocalizationConfig $config,
        private readonly LocalizedUrlGenerator $urls,
        #[Tag(SitemapSource::class)]
        private readonly iterable $sources = [],
    ) {}

    public function handle(): string
    {
        $entries = [
            ...$this->staticEntries('home'),
            ...$this->pageEntries(),
            ...$this->staticEntries('articles.index'),
            ...$this->articleEntries(),
            ...$this->sourceEntries(),
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$this->escape($entry['loc'])."</loc>\n";

            foreach ($entry['alternates'] as $hreflang => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.$this->escape($hreflang).'" href="'.$this->escape($href).'"/>'."\n";
            }

            if ($entry['lastmod'] !== null) {
                $xml .= '    <lastmod>'.$this->escape($entry['lastmod'])."</lastmod>\n";
            }

            $xml .= "  </url>\n";
        }

        return $xml."</urlset>\n";
    }

    /**
     * @return list<array{loc: string, alternates: array<string, string>, lastmod: string|null}>
     */
    private function staticEntries(string $routeName): array
    {
        $locales = $this->config->getPublicLocales();
        $alternates = count($locales) > 1 ? $this->urls->getAlternateUrls($routeName) : [];

        return array_map(
            fn (string $locale): array => [
                'loc' => $this->urls->url($routeName, [], $locale),
                'alternates' => $alternates,
                'lastmod' => null,
            ],
            $locales,
        );
    }

    /**
     * @return list<array{loc: string, alternates: array<string, string>, lastmod: string|null}>
     */
    private function pageEntries(): array
    {
        $entries = [];

        foreach ($this->pages->publishedForSitemap() as $page) {
            $entries = [...$entries, ...$this->translatedEntries('pages.show', $page->translations, $page->updated_at)];
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, alternates: array<string, string>, lastmod: string|null}>
     */
    private function articleEntries(): array
    {
        $entries = [];

        foreach ($this->articles->publishedForSitemap() as $article) {
            $entries = [...$entries, ...$this->translatedEntries('articles.show', $article->translations, $article->updated_at)];
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, alternates: array<string, string>, lastmod: string|null}>
     */
    private function sourceEntries(): array
    {
        $entries = [];

        foreach ($this->sources as $source) {
            foreach ($source->entries() as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Entries of the visible translations of one record, linked to each
     * other as hreflang alternates.
     *
     * @param  Collection<int, PageTranslation>|Collection<int, ArticleTranslation>  $visibleTranslations
     * @return list<array{loc: string, alternates: array<string, string>, lastmod: string|null}>
     */
    private function translatedEntries(string $routeName, Collection $visibleTranslations, ?DateTimeInterface $recordUpdatedAt): array
    {
        $publicLocales = $this->config->getPublicLocales();
        $defaultLocale = $this->config->getPublicDefault();

        $translations = $visibleTranslations
            ->filter(fn (PageTranslation|ArticleTranslation $translation): bool => in_array($translation->locale, $publicLocales, true));

        $alternates = [];
        foreach ($translations as $translation) {
            $alternates[$translation->locale] = $this->urls->url($routeName, ['slug' => $translation->slug], $translation->locale);
        }

        if (isset($alternates[$defaultLocale])) {
            $alternates['x-default'] = $alternates[$defaultLocale];
        }

        $entries = [];
        foreach ($translations as $translation) {
            $entries[] = [
                'loc' => $alternates[$translation->locale],
                'alternates' => $translations->count() > 1 ? $alternates : [],
                'lastmod' => ($translation->updated_at ?? $recordUpdatedAt)?->format(DateTimeInterface::ATOM),
            ];
        }

        return $entries;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
