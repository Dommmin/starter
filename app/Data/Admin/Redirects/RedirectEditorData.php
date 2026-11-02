<?php

namespace App\Data\Admin\Redirects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/redirects/create` and `admin/redirects/edit` screens.
 */
#[TypeScript]
class RedirectEditorData extends Data
{
    public function __construct(
        public RedirectFormData $redirect,
        public RedirectAbilitiesData $can,
    ) {}
}
