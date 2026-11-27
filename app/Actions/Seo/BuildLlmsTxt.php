<?php

namespace App\Actions\Seo;

use App\Models\Faq;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizedUrlGenerator;

/**
 * Build `/llms.txt` in Markdown format for AI/LLM crawlers: site identity,
 * published pages, latest articles and active FAQs per public locale.
 */
class BuildLlmsTxt
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettings,
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly LocalizationConfig $config,
        private readonly LocalizedUrlGenerator $urls,
    ) {}

    public function handle(): string
    {
        $defaultLocale = $this->config->getPublicDefault();
        $settings = $this->siteSettings->current($defaultLocale);
        $siteName = $settings->name !== '' ? $settings->name : (string) config('app.name');

        $lines = ["# {$siteName}", ''];

        $description = $settings->tagline ?? $settings->footerText;

        if (is_string($description) && trim($description) !== '') {
            $lines[] = '> '.trim(preg_replace('/\s+/', ' ', $description) ?? '');
            $lines[] = '';
        }

        if ($this->siteSettings->isNoindex() || $this->siteSettings->isComingSoon()) {
            $lines[] = 'Site indexing is currently disabled.';

            return implode("\n", $lines)."\n";
        }

        $pages = $this->pages->publishedForSitemap();
        $pageLines = [];
        foreach ($pages as $page) {
            foreach ($page->translations as $translation) {
                $url = $this->urls->url('pages.show', ['slug' => $translation->slug], $translation->locale);
                $meta = $translation->meta_description ? ": {$translation->meta_description}" : '';
                $pageLines[] = "- [{$translation->title}]({$url}) ({$translation->locale}){$meta}";
            }
        }

        if ($pageLines !== []) {
            $lines[] = '## Pages';
            $lines[] = '';
            $lines = [...$lines, ...$pageLines, ''];
        }

        $articles = $this->articles->publishedForSitemap();
        $articleLines = [];
        foreach ($articles->take(30) as $article) {
            foreach ($article->translations as $translation) {
                $url = $this->urls->url('articles.show', ['slug' => $translation->slug], $translation->locale);
                $summary = $translation->excerpt ?: $translation->meta_description;
                $meta = $summary ? ": {$summary}" : '';
                $articleLines[] = "- [{$translation->title}]({$url}) ({$translation->locale}){$meta}";
            }
        }

        if ($articleLines !== []) {
            $lines[] = '## Articles';
            $lines[] = '';
            $lines = [...$lines, ...$articleLines, ''];
        }

        $faqs = Faq::query()
            ->where('published', true)
            ->orderBy('position')
            ->limit(25)
            ->get(['locale', 'question', 'answer']);

        if ($faqs->isNotEmpty()) {
            $lines[] = '## FAQ';
            $lines[] = '';
            foreach ($faqs as $faq) {
                $answer = trim(preg_replace('/\s+/', ' ', strip_tags((string) $faq->answer)) ?? '');
                $lines[] = "- **{$faq->question}** ({$faq->locale}): {$answer}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
