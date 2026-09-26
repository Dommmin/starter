<?php

namespace App\Data\Listing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The `pagination` part of every server-side list payload (see ListQuery).
 */
#[TypeScript]
class ListPaginationData extends Data
{
    public function __construct(
        public int $page,
        public int $totalPages,
        public int $total,
        public int $perPage,
    ) {}
}
