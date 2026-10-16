<?php

namespace App\Data\Admin\ContactForms;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/contact-forms/index` screen.
 */
#[TypeScript]
class ContactFormIndexData extends Data
{
    /**
     * @param  list<ContactFormListItemData>  $items
     */
    public function __construct(
        public array $items,
        public bool $canCreate,
    ) {}
}
