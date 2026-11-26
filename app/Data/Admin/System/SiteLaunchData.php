<?php

namespace App\Data\Admin\System;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * `launch` prop of the System page: the publication and maintenance
 * switches and the launch checklist. `maintenanceUntil` is the planned end
 * (ISO 8601, null = not given). `testMailRecipient` is the signed-in
 * administrator's address.
 */
#[TypeScript]
class SiteLaunchData extends Data
{
    /**
     * @param  list<LaunchCheckData>  $checklist
     */
    public function __construct(
        public bool $comingSoon,
        public bool $noindex,
        public bool $maintenance,
        public ?string $maintenanceUntil,
        public array $checklist,
        public string $testMailRecipient,
    ) {}
}
