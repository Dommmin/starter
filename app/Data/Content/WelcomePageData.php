<?php

namespace App\Data\Content;

use App\Data\Contact\ContactFormData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `welcome` (home) page.
 */
#[TypeScript]
class WelcomePageData extends Data
{
    public function __construct(
        public ContactFormData $contactForm,
    ) {}
}
