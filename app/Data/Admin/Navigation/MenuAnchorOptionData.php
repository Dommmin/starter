<?php

namespace App\Data\Admin\Navigation;

use App\Enums\HomeSectionAnchor;
use App\Enums\HomeSectionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A selectable home page section anchor ({@see HomeSectionAnchor}) for an
 * anchor item without a page. `enabled` tells whether the section is shown
 * on the home page in the menu locale; a hidden one stays selectable.
 */
#[TypeScript]
class MenuAnchorOptionData extends Data
{
    public function __construct(
        public string $anchor,
        public HomeSectionType $sectionType,
        public bool $enabled,
    ) {}
}
