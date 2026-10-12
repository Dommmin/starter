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
use App\Data\Home\HomePortfolioData;
use App\Data\Home\HomePortfolioItemData;
use App\Data\Home\HomePricingData;
use App\Data\Home\HomePricingPlanData;
use App\Data\Home\HomeSectionData;
use App\Data\Home\HomeTeamData;
use App\Data\Home\HomeTeamMemberData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\PortfolioContentData;
use App\Data\Home\PricingContentData;
use App\Data\Home\TeamContentData;
use App\Data\Home\TestimonialsContentData;
use App\Data\Media\MediaImageData;
use App\Enums\HomeLinkTarget;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\PageTranslation;
use App\Models\User;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Repositories\Home\HomeSectionRepository;
use App\Repositories\Media\MediaAssetRepository;
use App\Services\Home\HomeLinkResolver;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * Build the enabled sections of the public home page in a locale: display
 * order, action URLs resolved for the locale, FAQ questions, the newest
 * articles and DAM images loaded. No enabled section means an empty list.
 */
class BuildHomeSections
{
    public function __construct(
        private readonly HomeSectionRepository $sections,
        private readonly ArticleRepository $articles,
        private readonly PageRepository $pages,
        private readonly HomeLinkResolver $links,
        private readonly LocalizedUrlGenerator $urls,
        private readonly MediaAssetRepository $media,
    ) {}

    /**
     * @param  User|null  $viewer  Signed-in user; guest-only actions are replaced or dropped for them.
     * @return list<HomeSectionData>
     */
    public function handle(string $locale, ?User $viewer = null): array
    {
        $sections = $this->sections->enabledFor($locale);
        $publishedPages = $this->pages->publishedInLocale($locale, $this->linkedPageIds($sections->all()));
        $images = $this->media->usableImagesByIds($this->imageIds($sections->all()));

        $result = [];
        foreach ($sections as $section) {
            $result[] = new HomeSectionData(
                id: $section->id,
                type: $section->type,
                anchor: $section->type->anchor(),
                content: $this->content($section, $locale, $publishedPages, $images, $viewer),
            );
        }

        return $result;
    }

    /**
     * @param  array<int, PageTranslation>  $publishedPages
     * @param  Collection<int, MediaAsset>  $images
     */
    private function content(HomeSection $section, string $locale, array $publishedPages, Collection $images, ?User $viewer): HomeHeroData|FeaturesContentData|HomeFaqData|TestimonialsContentData|HomeLatestArticlesData|ContactContentData|HomeCtaData|HomePortfolioData|HomeTeamData|HomePricingData
    {
        $content = $section->content;

        return match (true) {
            $content instanceof HeroContentData => new HomeHeroData(
                eyebrow: $content->eyebrow,
                title: $content->title,
                description: $content->description,
                primaryAction: $this->links->resolve($content->primaryAction, $locale, $publishedPages, $viewer),
                secondaryAction: $this->links->resolve($content->secondaryAction, $locale, $publishedPages, $viewer),
                image: $this->image($content->imageId, $images),
                imageAlt: $this->imageAlt($content->imageId, $images),
                stats: $content->stats,
            ),
            $content instanceof CtaContentData => new HomeCtaData(
                title: $content->title,
                description: $content->description,
                primaryAction: $this->links->resolve($content->primaryAction, $locale, $publishedPages, $viewer),
                secondaryAction: $this->links->resolve($content->secondaryAction, $locale, $publishedPages, $viewer),
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
            $content instanceof PortfolioContentData => new HomePortfolioData(
                title: $content->title,
                description: $content->description,
                items: array_map(fn ($item): HomePortfolioItemData => new HomePortfolioItemData(
                    title: $item->title,
                    category: $item->category,
                    description: $item->description,
                    image: $this->image($item->imageId, $images),
                    imageAlt: $this->imageAlt($item->imageId, $images),
                    url: $item->url,
                ), $content->items),
            ),
            $content instanceof TeamContentData => new HomeTeamData(
                title: $content->title,
                description: $content->description,
                items: array_map(fn ($member): HomeTeamMemberData => new HomeTeamMemberData(
                    name: $member->name,
                    role: $member->role,
                    bio: $member->bio,
                    photo: $this->image($member->photoId, $images),
                    photoAlt: $this->imageAlt($member->photoId, $images),
                    skills: $member->skills,
                ), $content->items),
            ),
            $content instanceof PricingContentData => new HomePricingData(
                title: $content->title,
                description: $content->description,
                items: array_map(fn ($plan): HomePricingPlanData => new HomePricingPlanData(
                    name: $plan->name,
                    priceMinor: $plan->priceMinor,
                    currency: $plan->currency,
                    priceFrom: $plan->priceFrom,
                    period: $plan->period,
                    description: $plan->description,
                    features: $plan->features,
                    highlighted: $plan->highlighted,
                    action: $this->links->resolve($plan->action, $locale, $publishedPages, $viewer),
                ), $content->items),
                note: $content->note,
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
     * Responsive picture of a referenced DAM image; null when the asset was
     * deleted or is no longer a usable image.
     *
     * @param  Collection<int, MediaAsset>  $images
     */
    private function image(?int $id, Collection $images): ?MediaImageData
    {
        $asset = $id === null ? null : $images->get($id);

        return $asset === null ? null : MediaImageData::fromAsset($asset);
    }

    /**
     * Alternative text of a referenced DAM image (it belongs to the asset).
     *
     * @param  Collection<int, MediaAsset>  $images
     */
    private function imageAlt(?int $id, Collection $images): string
    {
        return ($id === null ? null : $images->get($id))->alt ?? '';
    }

    /**
     * Ids of the DAM images referenced by section content.
     *
     * @param  array<int, HomeSection>  $sections
     * @return list<int>
     */
    private function imageIds(array $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $content = $section->content;

            if ($content instanceof HeroContentData && $content->imageId !== null) {
                $ids[] = $content->imageId;
            }

            if ($content instanceof PortfolioContentData) {
                foreach ($content->items as $item) {
                    if ($item->imageId !== null) {
                        $ids[] = $item->imageId;
                    }
                }
            }

            if ($content instanceof TeamContentData) {
                foreach ($content->items as $member) {
                    if ($member->photoId !== null) {
                        $ids[] = $member->photoId;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Ids of the CMS pages referenced by hero, CTA and pricing actions.
     *
     * @param  array<int, HomeSection>  $sections
     * @return list<int>
     */
    private function linkedPageIds(array $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $content = $section->content;

            $actions = match (true) {
                $content instanceof HeroContentData, $content instanceof CtaContentData => [$content->primaryAction, $content->secondaryAction],
                $content instanceof PricingContentData => array_map(fn ($plan): ?HomeActionData => $plan->action, $content->items),
                default => [],
            };

            foreach ($actions as $action) {
                if ($action instanceof HomeActionData && $action->target === HomeLinkTarget::Page && $action->pageId !== null) {
                    $ids[] = $action->pageId;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
