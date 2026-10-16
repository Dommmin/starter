<?php

namespace App\Data\Admin\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One extra form value of a contact message, ready to display.
 */
#[TypeScript]
class ContactMessageExtraFieldData extends Data
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
