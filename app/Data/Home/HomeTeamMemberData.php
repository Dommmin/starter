<?php

namespace App\Data\Home;

use App\Data\Media\MediaImageData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered person of the `team` section; a photo that is no longer a
 * usable DAM image is null.
 */
#[TypeScript]
class HomeTeamMemberData extends Data
{
    /**
     * @param  list<string>  $skills
     */
    public function __construct(
        public string $name,
        public string $role,
        public ?string $bio,
        public ?MediaImageData $photo,
        public string $photoAlt,
        public array $skills,
    ) {}
}
