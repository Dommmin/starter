<?php

namespace App\Data\Admin\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/contact/show` screen.
 */
#[TypeScript]
class ContactMessageShowData extends Data
{
    public function __construct(
        public ContactMessageDetailData $contactMessage,
        public ContactMessageAbilitiesData $can,
    ) {}
}
