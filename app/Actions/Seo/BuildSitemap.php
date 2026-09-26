<?php

namespace App\Actions\Seo;

use App\Models\Page;
use App\Repositories\Content\PageRepository;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizedUrlGenerator;
use DateTimeInterface;

/**
 * Build the XML sitemap of canonical, indexable public URLs: the home page
 * in every active public locale and every published page translation, with
 * reciprocal hreflang alternates between language versions.
 */
class BuildSitemap
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly LocalizationConfig $config,
        private readonly LocalizedUrlGenerator $urls,
    ) {}

    public function handle(): string
    {
        $entries = [...$this->homeEntries(), ...$this->pageEntries()];

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
    private function homeEntries(): array
    {
        $locales = $this->config->getPublicLocales();
        $alternates = count($locales) > 1 ? $this->urls->getAlternateUrls('home') : [];

        return array_map(
            fn (string $locale): array => [
                'loc' => $this->urls->url('home', [], $locale),
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
        $publicLocales = $this->config->getPublicLocales();
        $defaultLocale = $this->config->getPublicDefault();
        $entries = [];

        foreach ($this->pages->publishedForSitemap() as $page) {
            $translations = $page->translations
                ->filter(fn ($translation): bool => in_array($translation->locale, $publicLocales, true));

            $alternates = [];
            foreach ($translations as $translation) {
                $alternates[$translation->locale] = $this->urls->url('pages.show', ['slug' => $translation->slug], $translation->locale);
            }

            if (isset($alternates[$defaultLocale])) {
                $alternates['x-default'] = $alternates[$defaultLocale];
            }

            foreach ($translations as $translation) {
                $entries[] = [
                    'loc' => $alternates[$translation->locale],
                    'alternates' => $translations->count() > 1 ? $alternates : [],
                    'lastmod' => $this->lastModified($page, $translation->updated_at),
                ];
            }
        }

        return $entries;
    }

    private function lastModified(Page $page, ?DateTimeInterface $updatedAt): ?string
    {
        return ($updatedAt ?? $page->updated_at)?->format(DateTimeInterface::ATOM);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
