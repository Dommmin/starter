<?php

namespace App\Data\Admin\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Effective list state echoed back to the contact message list.
 */
#[TypeScript]
class ContactMessageListFiltersData extends Data
{
    public function __construct(
        public string $search,
        #[LiteralTypeScriptType("'created_at'")]
        public string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $direction,
        #[LiteralTypeScriptType("'all' | App.Enums.ContactMessageStatus")]
        public string $status,
    ) {}
}
