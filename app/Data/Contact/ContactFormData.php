<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server state of the public contact form: the encrypted token carrying
 * the render time (spam timing check).
 */
#[TypeScript]
class ContactFormData extends Data
{
    public function __construct(
        public string $token,
    ) {}
}
