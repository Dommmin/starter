<?php

namespace App\Data\Admin\ContactForms;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/contact-forms/create` and `edit` screens.
 */
#[TypeScript]
class ContactFormEditorData extends Data
{
    public function __construct(
        public ContactFormFormData $form,
        public ContentLocalesData $locales,
        public bool $canDelete,
        public int $maxFields,
        public int $maxOptions,
    ) {}
}
