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
    */
    'features' => [
        'seo_meta' => (bool) env('AI_FEATURE_SEO_META', true),
        'faq_generator' => (bool) env('AI_FEATURE_FAQ_GENERATOR', true),
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
    | Request timeout in seconds; stays below the PHP-FPM request timeout.
    */
    'timeout' => (int) env('AI_TIMEOUT', 30),

];
