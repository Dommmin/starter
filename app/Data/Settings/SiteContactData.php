<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Public contact details of the site. `address` joins the street line,
 * postal code with city and country code with line breaks (`\n`).
 */
#[TypeScript]
class SiteContactData extends Data
{
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public ?string $address,
    ) {}
}
