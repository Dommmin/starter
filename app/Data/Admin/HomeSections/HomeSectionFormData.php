<?php

namespace App\Data\Admin\HomeSections;

use App\Data\Home\ContactContentData;
use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Editor state of one section: the stored content schema of its type
 * (discriminated union on `type` in TypeScript). `updatedAt` must be sent
 * back on update for optimistic locking.
 */
#[TypeScript]
#[LiteralTypeScriptType(
    '{ id: number; locale: string; enabled: boolean; updatedAt: string | null } & ('
    ."{ type: 'hero'; content: %hero% }"
    ." | { type: 'features'; content: %features% }"
    ." | { type: 'faq'; content: %faq% }"
    ." | { type: 'testimonials'; content: %testimonials% }"
    ." | { type: 'latest_articles'; content: %latestArticles% }"
    ." | { type: 'contact'; content: %contact% }"
    ." | { type: 'cta'; content: %cta% }"
    .')',
    references: [
        'hero' => HeroContentData::class,
        'features' => FeaturesContentData::class,
        'faq' => FaqContentData::class,
        'testimonials' => TestimonialsContentData::class,
        'latestArticles' => LatestArticlesContentData::class,
        'contact' => ContactContentData::class,
        'cta' => CtaContentData::class,
    ],
)]
class HomeSectionFormData extends Data
{
    public function __construct(
        public int $id,
        public string $locale,
        public bool $enabled,
        public ?string $updatedAt,
        public HomeSectionType $type,
        public Data $content,
    ) {}

    public static function fromModel(HomeSection $section): self
    {
        return new self(
            id: $section->id,
            locale: $section->locale,
            enabled: $section->enabled,
            updatedAt: $section->updated_at?->toIso8601String(),
            type: $section->type,
            content: $section->content,
        );
    }
}
