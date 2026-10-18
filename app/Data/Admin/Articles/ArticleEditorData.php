<?php

namespace App\Data\Admin\Articles;

use App\Data\Content\ContentLocalesData;
use App\Data\Listing\RecordOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/articles/create` and `admin/articles/edit` screens.
 */
#[TypeScript]
class ArticleEditorData extends Data
{
    /**
     * @param  int  $trashDays  Days a deleted record stays in the trash.
     * @param  array<string, string>  $previewUrls  Locale => signed admin preview URL of each saved translation.
     * @param  list<RecordOptionData>  $tagOptions  Every tag, named in the panel locale.
     */
    public function __construct(
        public ArticleFormData $article,
        public ContentLocalesData $locales,
        public ArticleAbilitiesData $can,
        public array $previewUrls = [],
        public array $tagOptions = [],
        public int $maxTags = 0,
        public int $trashDays = 30,
    ) {}
}
