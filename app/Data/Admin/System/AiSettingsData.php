<?php

namespace App\Data\Admin\System;

use App\Enums\AiProvider;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * `ai` prop of the System page. The API key itself is never sent:
 * `hasPanelKey` only says whether the panel stores one. `provider` is the
 * panel provider (null = none set). `environmentConfigured` says whether
 * the AI_PROVIDER and provider key environment values configure a fallback;
 * `assistantEnabled` mirrors AI_FEATURES_ENABLED.
 */
#[TypeScript]
class AiSettingsData extends Data
{
    public function __construct(
        public ?AiProvider $provider,
        public bool $hasPanelKey,
        public bool $environmentConfigured,
        public bool $assistantEnabled,
    ) {}
}
