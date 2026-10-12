<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `team` section (at most MAX_ITEMS people).
 */
#[TypeScript]
class TeamContentData extends Data
{
    public const int MAX_ITEMS = 8;

    /**
     * @param  list<TeamMemberData>  $items
     */
    public function __construct(
        #[DataCollectionOf(TeamMemberData::class)]
        public array $items = [],
        public ?string $title = null,
        public ?string $description = null,
    ) {}
}
