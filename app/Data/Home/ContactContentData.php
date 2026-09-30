<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `contact` section; the form itself (and its token)
 * is page-level (`WelcomePageData::$contactForm`), one per page.
 */
#[TypeScript]
class ContactContentData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description = null,
    ) {}
}
