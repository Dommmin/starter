<?php

namespace App\Data\Admin\Users;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 * `changeRole` is false on the administrator's own account.
 */
#[TypeScript]
class UserAbilitiesData extends Data
{
    public function __construct(
        public bool $delete,
        public bool $changeRole,
    ) {}
}
