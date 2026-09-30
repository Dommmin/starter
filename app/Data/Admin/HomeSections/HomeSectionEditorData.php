<?php

namespace App\Data\Admin\HomeSections;

use App\Data\Content\ContentLocaleData;
use App\Enums\HomeLinkTarget;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/home-sections/edit` screen: the section, the link
 * targets available in this installation and the pages published in the
 * section locale.
 */
#[TypeScript]
class HomeSectionEditorData extends Data
{
    /**
     * @param  list<HomeLinkTarget>  $linkTargets
     * @param  list<HomePageOptionData>  $pages
     */
    public function __construct(
        public HomeSectionFormData $section,
        public ContentLocaleData $locale,
        public array $linkTargets,
        public array $pages,
        public HomeSectionAbilitiesData $can,
    ) {}
}
