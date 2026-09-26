<?php

namespace App\Data\Content;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `pages/show` screen. `bodyHtml` is produced by
 * RichTextRenderer from the closed schema and is safe to inject.
 */
#[TypeScript]
class PublicPageData extends Data
{
    /**
     * @param  array<string, string>  $alternates  Locale (and `x-default`) => canonical URL of each published translation.
     */
    public function __construct(
        public string $title,
        public ?string $metaDescription,
        public string $bodyHtml,
        public string $locale,
        public ?string $publishedAt,
        public array $alternates,
    ) {}
}
