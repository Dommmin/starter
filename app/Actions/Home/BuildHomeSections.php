<?php

namespace App\Actions\Home;

use App\Data\Content\ArticleSummaryData;
use App\Data\Home\ContactContentData;
use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\HomeActionData;
use App\Data\Home\HomeCtaData;
use App\Data\Home\HomeFaqData;
use App\Data\Home\HomeFaqItemData;
use App\Data\Home\HomeHeroData;
use App\Data\Home\HomeLatestArticlesData;
use App\Data\Home\HomeSectionData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeLinkTarget;
use App\Models\HomeSection;
use App\Models\PageTranslation;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Repositories\Home\HomeSectionRepository;
use App\Services\Home\HomeLinkResolver;
use App\Services\Localization\LocalizedUrlGenerator;
use LogicException;

/**
 * Build the enabled sections of the public home page in a locale: display
 * order, action URLs resolved for the locale, FAQ questions and the newest
 * articles loaded. No enabled section means an empty list.
 */
class BuildHomeSections
{
    public function __construct(
        private readonly HomeSectionRepository $sections,
        private readonly ArticleRepository $articles,
        private readonly PageRepository $pages,
        private readonly HomeLinkResolver $links,
        private readonly LocalizedUrlGenerator $urls,
    ) {}

    /**
     * @return list<HomeSectionData>
     */
    public function handle(string $locale): array
    {
        $sections = $this->sections->enabledFor($locale);
        $publishedPages = $this->pages->publishedInLocale($locale, $this->linkedPageIds($sections->all()));

        $result = [];
        foreach ($sections as $section) {
            $result[] = new HomeSectionData(
                id: $section->id,
                type: $section->type,
                anchor: $section->type->anchor(),
                content: $this->content($section, $locale, $publishedPages),
            );
        }

        return $result;
    }

    /**
     * @param  array<int, PageTranslation>  $publishedPages
     */
    private function content(HomeSection $section, string $locale, array $publishedPages): HomeHeroData|FeaturesContentData|HomeFaqData|TestimonialsContentData|HomeLatestArticlesData|ContactContentData|HomeCtaData
    {
        $content = $section->content;

        return match (true) {
            $content instanceof HeroContentData => new HomeHeroData(
                eyebrow: $content->eyebrow,
                title: $content->title,
                description: $content->description,
                primaryAction: $this->links->resolve($content->primaryAction, $locale, $publishedPages),
                secondaryAction: $this->links->resolve($content->secondaryAction, $locale, $publishedPages),
            ),
            $content instanceof CtaContentData => new HomeCtaData(
                title: $content->title,
                description: $content->description,
                primaryAction: $this->links->resolve($content->primaryAction, $locale, $publishedPages),
                secondaryAction: $this->links->resolve($content->secondaryAction, $locale, $publishedPages),
            ),
            $content instanceof FaqContentData => new HomeFaqData(
                title: $content->title,
                description: $content->description,
                items: array_values($this->sections->publishedFaqs($locale, $content->limit)
                    ->map(fn ($faq) => HomeFaqItemData::fromModel($faq))
                    ->all()),
            ),
            $content instanceof LatestArticlesContentData => new HomeLatestArticlesData(
                title: $content->title,
                items: $this->latestArticles($locale, $content->limit),
                listUrl: $this->urls->url('articles.index', [], $locale),
            ),
            $content instanceof FeaturesContentData,
            $content instanceof TestimonialsContentData,
            $content instanceof ContactContentData => $content,
            default => throw new LogicException("Unsupported content of home section [{$section->id}]."),
        };
    }

    /**
     * @return list<ArticleSummaryData>
     */
    private function latestArticles(string $locale, int $limit): array
    {
        $items = [];
        foreach ($this->articles->latestPublished($locale, $limit) as $translation) {
            $items[] = ArticleSummaryData::fromTranslation(
                $translation,
                $this->urls->url('articles.show', ['slug' => $translation->slug], $locale),
            );
        }

        return $items;
    }

    /**
     * Ids of the CMS pages referenced by hero/CTA actions.
     *
     * @param  array<int, HomeSection>  $sections
     * @return list<int>
     */
    private function linkedPageIds(array $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $content = $section->content;

            if (! $content instanceof HeroContentData && ! $content instanceof CtaContentData) {
                continue;
            }

            foreach ([$content->primaryAction, $content->secondaryAction] as $action) {
                if ($action instanceof HomeActionData && $action->target === HomeLinkTarget::Page && $action->pageId !== null) {
                    $ids[] = $action->pageId;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
