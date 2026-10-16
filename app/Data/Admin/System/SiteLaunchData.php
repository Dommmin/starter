<?php

namespace App\Data\Admin\System;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * `launch` prop of the System page: the publication switches and the launch
 * checklist. `testMailRecipient` is the signed-in administrator's address.
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
        public array $checklist,
        public string $testMailRecipient,
    ) {}
}
