<?php

namespace App\Data\Admin\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class ContactMessageAbilitiesData extends Data
{
    public function __construct(
        public bool $retry,
        public bool $delete,
    ) {}
}
