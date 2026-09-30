<?php

namespace App\Data\Content;

use App\Data\Media\MediaImageData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `articles/show` screen. `bodyHtml` is produced by
 * RichTextRenderer from the closed schema and is safe to inject.
 */
#[TypeScript]
class PublicArticleData extends Data
{
    /**
     * @param  array<string, string>  $alternates  Locale (and `x-default`) => canonical URL of each visible translation.
     */
    public function __construct(
        public string $title,
        public ?string $excerpt,
        public ?string $metaDescription,
        public string $bodyHtml,
        public string $locale,
        public ?string $publishedAt,
        public ?string $updatedAt,
        public ?MediaImageData $cover,
        public string $coverAlt,
        public string $listUrl,
        public array $alternates,
    ) {}
}
