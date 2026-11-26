<?php

namespace App\Enums;

use App\Support\Assistant\AiCredentials;

/**
 * Closed set of admin assistant features; values are the keys of
 * `assistant.features`.
 */
enum AssistantFeature: string
{
    case SeoMeta = 'seo_meta';
    case MediaAltText = 'media_alt_text';
    case Translation = 'translation';
    case TextRewrite = 'text_rewrite';
    case MessageClassification = 'message_classification';
    case FaqGenerator = 'faq_generator';

    /**
     * Whether the feature may be used: the assistant and the feature are
     * enabled and the default AI provider has an API key. The provider and
     * key set in the panel take precedence over the environment
     * ({@see AiCredentials}).
     */
    public function isAvailable(): bool
    {
        if (config('assistant.enabled') !== true || config("assistant.features.{$this->value}") !== true) {
            return false;
        }

        app(AiCredentials::class)->apply();

        $provider = config('ai.default');

        return is_string($provider) && filled(config("ai.providers.{$provider}.key"));
    }
}
