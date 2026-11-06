<?php

namespace App\Enums;

/**
 * Closed set of admin assistant features; values are the keys of
 * `assistant.features`.
 */
enum AssistantFeature: string
{
    case SeoMeta = 'seo_meta';
    case MediaAltText = 'media_alt_text';
    case TextRewrite = 'text_rewrite';

    /**
     * Whether the feature may be used: the assistant and the feature are
     * enabled and the default AI provider has an API key.
     */
    public function isAvailable(): bool
    {
        $provider = config('ai.default');

        return config('assistant.enabled') === true
            && config("assistant.features.{$this->value}") === true
            && is_string($provider)
            && filled(config("ai.providers.{$provider}.key"));
    }
}
