<?php

namespace App\Data\Admin\Articles;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/articles/create` and `admin/articles/edit` screens.
 */
#[TypeScript]
class ArticleEditorData extends Data
{
    /**
     * @param  array<string, string>  $previewUrls  Locale => signed admin preview URL of each saved translation.
     */
    public function __construct(
        public ArticleFormData $article,
        public ContentLocalesData $locales,
        public ArticleAbilitiesData $can,
        public array $previewUrls = [],
    ) {}
}
