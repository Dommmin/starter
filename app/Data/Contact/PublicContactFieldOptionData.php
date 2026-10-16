<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One option of a select field in the current locale.
 */
#[TypeScript]
class PublicContactFieldOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
