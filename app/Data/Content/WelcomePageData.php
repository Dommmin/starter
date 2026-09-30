<?php

namespace App\Data\Content;

use App\Data\Contact\ContactFormData;
use App\Data\Home\HomeSectionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `welcome` (home) page: the enabled sections of the
 * current locale in display order and the page-level contact form state.
 */
#[TypeScript]
class WelcomePageData extends Data
{
    /**
     * @param  list<HomeSectionData>  $sections
     */
    public function __construct(
        public ContactFormData $contactForm,
        public array $sections,
    ) {}
}
