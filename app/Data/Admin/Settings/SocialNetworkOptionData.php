<?php

namespace App\Data\Admin\Settings;

use App\Enums\SocialNetwork;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One allowed social network of the settings form, in display order.
 */
#[TypeScript]
class SocialNetworkOptionData extends Data
{
    public function __construct(
        public SocialNetwork $network,
        public string $label,
    ) {}

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return array_map(
            fn (SocialNetwork $network): self => new self(network: $network, label: $network->label()),
            SocialNetwork::cases(),
        );
    }
}
