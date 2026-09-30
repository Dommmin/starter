<?php

namespace App\Enums;

use App\Data\Home\ContactContentData;
use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialsContentData;
use Spatie\LaravelData\Data;

/**
 * Closed set of home page section types. Each public locale has exactly one
 * section of every type (unique locale + type); declaration order is the
 * default order of a new locale.
 */
enum HomeSectionType: string
{
    case Hero = 'hero';
    case Features = 'features';
    case Faq = 'faq';
    case Testimonials = 'testimonials';
    case LatestArticles = 'latest_articles';
    case Contact = 'contact';
    case Cta = 'cta';

    /**
     * Stable anchor id of the rendered section (navigation contract).
     */
    public function anchor(): HomeSectionAnchor
    {
        return match ($this) {
            self::Hero => HomeSectionAnchor::Hero,
            self::Features => HomeSectionAnchor::Features,
            self::Faq => HomeSectionAnchor::Faq,
            self::Testimonials => HomeSectionAnchor::Testimonials,
            self::LatestArticles => HomeSectionAnchor::LatestArticles,
            self::Contact => HomeSectionAnchor::Contact,
            self::Cta => HomeSectionAnchor::Cta,
        };
    }

    /**
     * Closed content schema stored in `home_sections.content`.
     *
     * @return class-string<Data>
     */
    public function contentClass(): string
    {
        return match ($this) {
            self::Hero => HeroContentData::class,
            self::Features => FeaturesContentData::class,
            self::Faq => FaqContentData::class,
            self::Testimonials => TestimonialsContentData::class,
            self::LatestArticles => LatestArticlesContentData::class,
            self::Contact => ContactContentData::class,
            self::Cta => CtaContentData::class,
        };
    }

    /**
     * Default position (1-based) of the type in a new locale.
     */
    public function defaultPosition(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }
}
