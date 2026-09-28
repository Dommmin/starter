<?php

namespace App\Data\Home;

use App\Enums\HomeSectionAnchor;
use App\Enums\HomeSectionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One enabled section of the public home page, ready to render.
 *
 * TypeScript sees a discriminated union on `type`, so the page can switch
 * exhaustively without casts. The PHP side guarantees the pairing: sections
 * are built only by BuildHomeSections from `HomeSectionType`.
 */
#[TypeScript]
#[LiteralTypeScriptType(
    '{ id: number; anchor: %anchor% } & ('
    ."{ type: 'hero'; content: %hero% }"
    ." | { type: 'features'; content: %features% }"
    ." | { type: 'faq'; content: %faq% }"
    ." | { type: 'testimonials'; content: %testimonials% }"
    ." | { type: 'latest_articles'; content: %latestArticles% }"
    ." | { type: 'contact'; content: %contact% }"
    ." | { type: 'cta'; content: %cta% }"
    .')',
    references: [
        'anchor' => HomeSectionAnchor::class,
        'hero' => HomeHeroData::class,
        'features' => FeaturesContentData::class,
        'faq' => HomeFaqData::class,
        'testimonials' => TestimonialsContentData::class,
        'latestArticles' => HomeLatestArticlesData::class,
        'contact' => ContactContentData::class,
        'cta' => HomeCtaData::class,
    ],
)]
class HomeSectionData extends Data
{
    public function __construct(
        public int $id,
        public HomeSectionType $type,
        public HomeSectionAnchor $anchor,
        public HomeHeroData|FeaturesContentData|HomeFaqData|TestimonialsContentData|HomeLatestArticlesData|ContactContentData|HomeCtaData $content,
    ) {}
}
