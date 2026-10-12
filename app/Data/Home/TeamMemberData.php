<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One person of the `team` section: an optional DAM photo (asset id) and
 * at most MAX_SKILLS short skill names.
 */
#[TypeScript]
class TeamMemberData extends Data
{
    public const int MAX_SKILLS = 10;

    /**
     * @param  list<string>  $skills
     */
    public function __construct(
        public string $name,
        public string $role,
        public ?string $bio = null,
        public ?int $photoId = null,
        public array $skills = [],
    ) {}
}
