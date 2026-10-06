<?php

namespace App\Data\Admin\Pages;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/pages/create` and `admin/pages/edit` screens.
 */
#[TypeScript]
class PageEditorData extends Data
{
    /**
     * @param  array<string, string>  $previewUrls  Locale => signed admin preview URL of each saved translation.
     */
    public function __construct(
        public PageFormData $page,
        public ContentLocalesData $locales,
        public PageAbilitiesData $can,
        public array $previewUrls = [],
    ) {}
}
