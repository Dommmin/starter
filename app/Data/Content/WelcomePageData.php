<?php

namespace App\Data\Content;

use App\Data\Contact\ContactFormData;
use App\Data\Home\HomeSectionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `welcome` (home) page: the enabled sections of the
 * current locale in display order, the page-level contact form state and
 * the JSON-LD of the business.
 */
#[TypeScript]
class WelcomePageData extends Data
{
    /**
     * @param  list<HomeSectionData>  $sections
     * @param  array<string, mixed>  $organizationJsonLd  Schema.org Organization or LocalBusiness of the site settings.
     */
    public function __construct(
        public ContactFormData $contactForm,
        public array $sections,
        #[LiteralTypeScriptType('Record<string, unknown>')]
        public array $organizationJsonLd = [],
    ) {}
}
