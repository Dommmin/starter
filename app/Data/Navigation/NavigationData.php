<?php

namespace App\Data\Navigation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Shared `navigation` prop of public pages: the visible header and footer
 * menus of the current locale.
 */
#[TypeScript]
class NavigationData extends Data
{
    /**
     * @param  list<NavigationItemData>  $header
     * @param  list<NavigationItemData>  $footer
     */
    public function __construct(
        public array $header,
        public array $footer,
    ) {}
}
