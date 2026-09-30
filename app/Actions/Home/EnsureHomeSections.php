<?php

namespace App\Actions\Home;

use App\Data\Home\ContactContentData;
use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use App\Services\Localization\LocalizationConfig;
use Closure;
use Spatie\LaravelData\Data;

/**
 * Make sure every public locale has exactly one section of every type.
 * Missing sections are created disabled at their default position; existing
 * ones are never touched, so the action is idempotent and safe to run on
 * every deploy or after a locale is added.
 */
class EnsureHomeSections
{
    public function __construct(
        private readonly LocalizationConfig $config,
    ) {}

    /**
     * @param  (Closure(HomeSectionType, string): Data)|null  $contentFor  Initial content of a new section; a neutral placeholder by default.
     * @param  list<HomeSectionType>  $enabledTypes  Types a new section starts enabled with.
     * @return int Number of sections created.
     */
    public function handle(?Closure $contentFor = null, array $enabledTypes = []): int
    {
        $contentFor ??= self::placeholder(...);
        $created = 0;

        foreach ($this->config->getPublicLocales() as $locale) {
            foreach (HomeSectionType::cases() as $type) {
                $section = HomeSection::query()->firstOrCreate(
                    ['locale' => $locale, 'type' => $type],
                    [
                        'position' => $type->defaultPosition(),
                        'enabled' => in_array($type, $enabledTypes, true),
                        'content' => $contentFor($type, $locale),
                    ],
                );

                if ($section->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Minimal valid content: the translated type name where a title is
     * required, empty otherwise.
     */
    public static function placeholder(HomeSectionType $type, string $locale): Data
    {
        $title = __("admin.homeSections.types.{$type->value}", [], $locale);

        return match ($type) {
            HomeSectionType::Hero => new HeroContentData(title: $title),
            HomeSectionType::Features => new FeaturesContentData,
            HomeSectionType::Faq => new FaqContentData,
            HomeSectionType::Testimonials => new TestimonialsContentData,
            HomeSectionType::LatestArticles => new LatestArticlesContentData,
            HomeSectionType::Contact => new ContactContentData(title: $title),
            HomeSectionType::Cta => new CtaContentData(title: $title),
        };
    }
}
