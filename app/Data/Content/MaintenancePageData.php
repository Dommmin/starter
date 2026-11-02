<?php

namespace App\Data\Content;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `public/maintenance` page shown (503) while the
 * maintenance switch is on. `minutesLeft` counts to the planned end, null
 * when no end was given or it has passed.
 */
#[TypeScript]
class MaintenancePageData extends Data
{
    public function __construct(
        public ?string $contactEmail,
        public ?int $minutesLeft,
    ) {}
}
