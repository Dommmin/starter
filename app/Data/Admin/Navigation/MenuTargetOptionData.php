<?php

namespace App\Data\Admin\Navigation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A selectable page/article (id of the page/article, not of the
 * translation) with its title in the menu locale. `draft` marks a target
 * that visitors cannot see yet in that locale (draft or scheduled).
 */
#[TypeScript]
class MenuTargetOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public bool $draft,
    ) {}
}
