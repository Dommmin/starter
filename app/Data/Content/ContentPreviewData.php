<?php

namespace App\Data\Content;

use App\Enums\ContentPreviewState;
use App\Enums\PublicationStatus;
use App\Models\ArticleTranslation;
use App\Models\PageTranslation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Preview banner of a public screen rendered from the admin panel: the
 * visibility of the saved translation and the way back to its editor.
 * Present only in the signed admin preview, never on public URLs.
 */
#[TypeScript]
class ContentPreviewData extends Data
{
    public function __construct(
        public ContentPreviewState $state,
        public ?string $publishAt,
        public string $editUrl,
    ) {}

    /**
     * Pages have no scheduling: a published translation is visible at once.
     */
    public static function forPage(PageTranslation $translation, string $editUrl): self
    {
        return new self(
            state: $translation->isPublished() ? ContentPreviewState::Published : ContentPreviewState::Draft,
            publishAt: null,
            editUrl: $editUrl,
        );
    }

    /**
     * A published article becomes visible at `published_at`; until then it is scheduled.
     */
    public static function forArticle(ArticleTranslation $translation, string $editUrl): self
    {
        $state = match (true) {
            $translation->status === PublicationStatus::Draft => ContentPreviewState::Draft,
            $translation->published_at === null, $translation->published_at->isFuture() => ContentPreviewState::Scheduled,
            default => ContentPreviewState::Published,
        };

        return new self(
            state: $state,
            publishAt: $state === ContentPreviewState::Scheduled ? $translation->published_at?->toIso8601String() : null,
            editUrl: $editUrl,
        );
    }
}
