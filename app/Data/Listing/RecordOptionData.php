<?php

namespace App\Data\Listing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One selectable related record (select fields and list filters of
 * generated admin resources).
 */
#[TypeScript]
class RecordOptionData extends Data
{
    /**
     * Maximum number of options sent to one select; a longer list is
     * truncated and the screen says so.
     */
    public const int LIMIT = 500;

    public function __construct(
        public int $id,
        public string $label,
    ) {}
}
