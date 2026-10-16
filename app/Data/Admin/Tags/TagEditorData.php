<?php

namespace App\Data\Admin\Tags;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/tags/create` and `edit` screens.
 */
#[TypeScript]
class TagEditorData extends Data
{
    public function __construct(
        public TagFormData $tag,
        public ContentLocalesData $locales,
        public bool $canDelete,
        public bool $canUpdate = true,
    ) {}
}
