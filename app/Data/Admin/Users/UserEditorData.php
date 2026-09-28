<?php

namespace App\Data\Admin\Users;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/users/create` and `admin/users/edit` screens.
 */
#[TypeScript]
class UserEditorData extends Data
{
    public function __construct(
        public UserFormData $user,
        public UserAbilitiesData $can,
    ) {}
}
