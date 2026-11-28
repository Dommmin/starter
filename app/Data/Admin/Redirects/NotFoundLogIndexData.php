<?php

namespace App\Data\Admin\Redirects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for the 404 monitor page in the redirects admin section.
 */
#[TypeScript]
class NotFoundLogIndexData extends Data
{
    /**
     * @param  list<NotFoundLogItemData>  $items
     */
    public function __construct(
        public array $items,
        public bool $canCreateRedirect,
    ) {}
}
