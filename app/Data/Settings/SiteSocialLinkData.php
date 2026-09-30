<?php

namespace App\Data\Settings;

use App\Enums\SocialNetwork;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Link to one social profile; `label` is the network's brand name.
 */
#[TypeScript]
class SiteSocialLinkData extends Data
{
    public function __construct(
        public SocialNetwork $network,
        public string $label,
        public string $url,
    ) {}
}
