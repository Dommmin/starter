<?php

namespace App\Data\Admin\Pages;

use App\Data\Admin\HomeSections\HomeSectionListItemData;
use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PageSectionsIndexData extends Data
{
    /**
     * @param  list<HomeSectionListItemData>  $items
     * @param  list<string>  $availableTypes
     * @param  list<ContentBlockData>  $blocks  Shared blocks of the language.
     * @param  list<int>  $sharedSectionIds  Listed sections that follow a block.
     */
    public function __construct(
        public int $pageId,
        public ?string $pageTitle,
        public string $locale,
        public ContentLocalesData $locales,
        public array $items,
        public array $availableTypes,
        public bool $canUpdate,
        public array $blocks = [],
        public array $sharedSectionIds = [],
    ) {}
}
