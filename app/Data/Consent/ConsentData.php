<?php

namespace App\Data\Consent;

use App\Enums\ConsentCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Shared `consent` prop of public pages: the categories the banner offers,
 * the visitor's valid choice (null shows the banner) and what the browser
 * needs to store a new one.
 */
#[TypeScript]
class ConsentData extends Data
{
    /**
     * @param  list<ConsentCategory>  $categories  Offered optional categories.
     * @param  list<ConsentCategory>|null  $granted  Allowed categories; null while undecided.
     */
    public function __construct(
        public int $version,
        public string $cookieName,
        public int $lifetimeDays,
        public array $categories,
        public ?array $granted,
        public ?string $policyUrl,
        public ?PlausibleData $plausible,
    ) {}
}
