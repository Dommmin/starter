<?php

namespace App\Data\Admin\Pages;

use App\Data\Admin\HomeSections\HomeSectionEditorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PageSectionEditorData extends Data
{
    public function __construct(
        public int $pageId,
        public ?string $pageTitle,
        public HomeSectionEditorData $editor,
    ) {}
}
