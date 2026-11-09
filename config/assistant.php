<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin assistant (docs/foundation/17-ai-admin-assistant-plan.md)
    |--------------------------------------------------------------------------
    |
    | AI suggestions in the admin panel. Disabled unless explicitly enabled
    | and an API key of the default provider (config/ai.php) is set; without
    | them the panel works as before and the assistant endpoints return 404.
    | Suggestions are only proposals: the admin accepts them in the form and
    | saves the record as usual. Prompts are never logged; the audit log keeps
    | the feature, model and token usage.
    |
    */

    'enabled' => (bool) env('AI_FEATURES_ENABLED', false),

    /*
    | Per-feature switches, keyed by App\Enums\AssistantFeature values.
    | `message_classification` sends the subject and body of contact messages
    | (personal data, sender details redacted) to the AI provider, so it is
    | off unless explicitly enabled (owner approval 2026-11-05).
    */
    'features' => [
        'seo_meta' => (bool) env('AI_FEATURE_SEO_META', true),
        // Manual alt-text suggestion and the automatic one for clean images
        // without alt text (queued, never overwrites a human alt text).
        'media_alt_text' => (bool) env('AI_FEATURE_MEDIA_ALT_TEXT', true),
        'translation' => (bool) env('AI_FEATURE_TRANSLATION', true),
        'text_rewrite' => (bool) env('AI_FEATURE_TEXT_REWRITE', true),
        'faq_generator' => (bool) env('AI_FEATURE_FAQ_GENERATOR', true),
        'message_classification' => (bool) env('AI_FEATURE_MESSAGE_CLASSIFICATION', false),
    ],

    /*
    | Soft monthly limit of input + output tokens of all features together,
    | counted in the cache store (not a billing source of truth). 0 disables
    | the assistant until the next month.
    */
    'monthly_token_budget' => (int) env('AI_MONTHLY_TOKEN_BUDGET', 2_000_000),

    /*
    | Suggestions per user and minute (named rate limiter `assistant`).
    */
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 20),

    /*
    | Contact message classifications per day across the site. The public
    | form triggers them, so a flood of (spam) messages from many addresses
    | must not use up the monthly budget of the admin features; beyond the
    | limit messages stay unclassified. 0 stops classification.
    */
    'message_classification_daily_limit' => (int) env('AI_MESSAGE_CLASSIFICATION_DAILY_LIMIT', 200),

    /*
    | Request timeout in seconds; stays below the PHP-FPM request timeout.
    */
    'timeout' => (int) env('AI_TIMEOUT', 30),

];
