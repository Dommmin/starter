<?php

namespace App\Data\Admin\Assistant;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON response of `admin.assistant.faqs`: proposed FAQ entries the admin
 * may tick and save; nothing is stored yet.
 */
#[TypeScript]
class FaqSuggestionData extends Data
{
    /**
     * @param  list<FaqSuggestionItemData>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
