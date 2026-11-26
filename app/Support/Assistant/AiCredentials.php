<?php

namespace App\Support\Assistant;

use App\Models\AiSetting;
use Laravel\Ai\AiManager;

/**
 * Applies the AI provider and key set in the panel on top of the
 * environment configuration: the panel wins, the AI_PROVIDER and provider
 * key environment values are the fallback. Singleton; each apply first
 * reverts its previous override, so a long-running queue worker picks up a
 * changed or removed panel key on the next job.
 */
class AiCredentials
{
    /**
     * Config values replaced by the last override, to restore them.
     *
     * @var array{default: mixed, keyPath: string, key: mixed}|null
     */
    private ?array $replaced = null;

    public function __construct(private readonly AiManager $manager) {}

    /**
     * Read the panel settings and point `ai.default` and the provider key at
     * them; without them the environment values stay in force.
     */
    public function apply(): void
    {
        $this->revert();

        $setting = AiSetting::query()->find(AiSetting::SINGLETON_ID);

        if ($setting === null || ! $setting->isConfigured() || $setting->provider === null) {
            return;
        }

        $provider = $setting->provider->value;
        $keyPath = "ai.providers.{$provider}.key";

        $this->replaced = [
            'default' => config('ai.default'),
            'keyPath' => $keyPath,
            'key' => config($keyPath),
        ];

        config(['ai.default' => $provider, $keyPath => $setting->api_key]);
        $this->manager->forgetInstance($provider);
    }

    /**
     * Whether the environment alone configures a provider key (shown on the
     * System page as the fallback).
     */
    public function environmentConfigured(): bool
    {
        $this->revert();
        $provider = config('ai.default');

        return is_string($provider) && filled(config("ai.providers.{$provider}.key"));
    }

    /**
     * Restore the config values replaced by the last override.
     */
    private function revert(): void
    {
        if ($this->replaced === null) {
            return;
        }

        $provider = config('ai.default');

        config([
            'ai.default' => $this->replaced['default'],
            $this->replaced['keyPath'] => $this->replaced['key'],
        ]);

        if (is_string($provider)) {
            $this->manager->forgetInstance($provider);
        }

        $this->replaced = null;
    }
}
