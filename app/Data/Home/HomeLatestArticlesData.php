<?php

namespace App\Data\Home;

use App\Data\Content\ArticleSummaryData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `latest_articles` section: newest published articles of the page
 * locale and the URL of the full list.
 */
#[TypeScript]
class HomeLatestArticlesData extends Data
{
    /**
     * @param  list<ArticleSummaryData>  $items
     */
    public function __construct(
        public ?string $title,
        public array $items,
        public string $listUrl,
    ) {}
}
