<?php

namespace App\Data\Consent;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Optional marketing, analytics and chat widget IDs configured in SiteSettings.
 * Loaded on public pages only after the corresponding cookie consent.
 */
#[TypeScript]
class MarketingIntegrationsData extends Data
{
    public function __construct(
        public ?string $ga4Id = null,
        public ?string $gtmId = null,
        public ?string $metaPixelId = null,
        public ?string $chatProvider = null,
        public ?string $chatWidgetId = null,
    ) {}
}
