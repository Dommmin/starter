<?php

namespace App\Data\Admin\HomeSections;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/home-sections/index` screen: every section of the
 * selected content locale in display order.
 */
#[TypeScript]
class HomeSectionIndexData extends Data
{
    /**
     * @param  list<HomeSectionListItemData>  $items
     */
    public function __construct(
        public array $items,
        public string $locale,
        public ContentLocalesData $locales,
        public HomeSectionAbilitiesData $can,
    ) {}
}
