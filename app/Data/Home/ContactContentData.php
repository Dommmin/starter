<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `contact` section; the form itself (and its token)
 * is page-level (`WelcomePageData::$contactForm`), one per page. `formKey`
 * picks the configured contact form (null: the default `contact` form).
 */
#[TypeScript]
class ContactContentData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $formKey = null,
    ) {}
}
