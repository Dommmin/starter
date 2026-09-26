<?php

namespace App\Data\Admin\Faqs;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/faqs/create` and `admin/faqs/edit` screens.
 */
#[TypeScript]
class FaqEditorData extends Data
{
    public function __construct(
        public FaqFormData $faq,
        public FaqAbilitiesData $can,
    ) {}
}
