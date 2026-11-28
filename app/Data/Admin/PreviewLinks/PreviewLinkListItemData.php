<?php

namespace App\Data\Admin\PreviewLinks;

use App\Models\ContentPreviewLink;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An active share link in the editor. It never carries the token.
 */
#[TypeScript]
class PreviewLinkListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $locale,
        public string $expiresAt,
        public ?string $authorName,
    ) {}

    public static function fromModel(ContentPreviewLink $link): self
    {
        return new self(
            id: $link->id,
            locale: $link->locale,
            expiresAt: $link->expires_at->toIso8601String(),
            authorName: $link->creator?->name,
        );
    }
}
