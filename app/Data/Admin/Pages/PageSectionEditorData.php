<?php

namespace App\Data\Admin\Pages;

use App\Data\Admin\HomeSections\HomeSectionEditorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PageSectionEditorData extends Data
{
    /**
     * @param  int|null  $sharedUses  Sections that follow the same block, or null for a section of its own.
     */
    public function __construct(
        public int $pageId,
        public ?string $pageTitle,
        public HomeSectionEditorData $editor,
        public ?int $sharedUses = null,
    ) {}
}
