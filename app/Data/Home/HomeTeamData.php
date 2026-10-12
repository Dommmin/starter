<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `team` section with its photos resolved.
 */
#[TypeScript]
class HomeTeamData extends Data
{
    /**
     * @param  list<HomeTeamMemberData>  $items
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public array $items,
    ) {}
}
