<?php

namespace App\Data\Content;

use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `articles/index` screen: newest visible articles of
 * the current locale.
 */
#[TypeScript]
class PublicArticleListData extends Data
{
    /**
     * @param  list<ArticleSummaryData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public string $locale,
    ) {}
}
