<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Public contact details of the site. `address` joins the street line,
 * postal code with city and country code with line breaks (`\n`).
 * `openingHours` lists seven days Monday first, or is null when the site
 * publishes no hours; `mapUrl` is an https link to a map.
 */
#[TypeScript]
class SiteContactData extends Data
{
    /**
     * @param  list<OpeningHoursDayData>|null  $openingHours
     */
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $taxId = null,
        public ?string $mapUrl = null,
        public ?array $openingHours = null,
    ) {}
}
