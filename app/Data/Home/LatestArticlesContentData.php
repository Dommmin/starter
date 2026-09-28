<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `latest_articles` section: the newest published
 * articles of the page locale, `limit` between 1 and MAX_LIMIT.
 */
#[TypeScript]
class LatestArticlesContentData extends Data
{
    public const int MAX_LIMIT = 6;

    public function __construct(
        public int $limit = 3,
        public ?string $title = null,
    ) {}
}
