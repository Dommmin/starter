<?php

namespace App\Data\Admin\Contact;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints of the message list; bulk deletion is authorized again per
 * message on the backend.
 */
#[TypeScript]
class ContactMessageListAbilitiesData extends Data
{
    public function __construct(
        public bool $delete,
    ) {}
}
