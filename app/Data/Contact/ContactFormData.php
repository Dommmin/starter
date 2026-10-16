<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server state of the public contact form: the encrypted token carrying
 * the render time (spam timing check) and the configured form with its
 * extra fields (null: name, e-mail and message only).
 */
#[TypeScript]
class ContactFormData extends Data
{
    public function __construct(
        public string $token,
        public ?PublicContactFormData $form = null,
    ) {}
}
