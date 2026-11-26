<?php

namespace App\Actions\Settings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AiProvider;
use App\Enums\AuditAction;
use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * Save or remove the AI provider and API key of the System page. The audit
 * entry records the provider and only the fact that the key changed, never
 * its value.
 */
class UpdateAiSettings
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * Save the provider; a null key keeps the stored one (the request
     * requires a key when none is stored or the provider changes).
     */
    public function update(AiProvider $provider, #[SensitiveParameter] ?string $apiKey, User $actor): AiSetting
    {
        return DB::transaction(function () use ($provider, $apiKey, $actor): AiSetting {
            $setting = $this->current();
            $changes = [];

            if ($setting->provider !== $provider) {
                $changes['provider'] = RecordAuditEvent::change($setting->provider?->value, $provider->value);
            }

            if (filled($apiKey)) {
                $changes['api_key'] = RecordAuditEvent::redacted();
                $setting->api_key = $apiKey;
            }

            $setting->provider = $provider;
            $setting->updated_by = $actor->id;
            $setting->save();

            $this->audit->handle(AuditAction::AiSettingsUpdated, $setting, $actor, $changes);

            return $setting;
        });
    }

    /**
     * Remove the provider and key; the environment values apply again.
     */
    public function clear(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            $setting = AiSetting::query()->lockForUpdate()->find(AiSetting::SINGLETON_ID);

            if ($setting === null || ($setting->provider === null && $setting->api_key === null)) {
                return;
            }

            $changes = [
                'provider' => RecordAuditEvent::change($setting->provider?->value, null),
                'api_key' => RecordAuditEvent::redacted(),
            ];

            $setting->provider = null;
            $setting->api_key = null;
            $setting->updated_by = $actor->id;
            $setting->save();

            $this->audit->handle(AuditAction::AiSettingsUpdated, $setting, $actor, $changes);
        });
    }

    /**
     * The singleton row, locked for the transaction; created on first save.
     */
    private function current(): AiSetting
    {
        $setting = AiSetting::query()->lockForUpdate()->find(AiSetting::SINGLETON_ID);

        if ($setting !== null) {
            return $setting;
        }

        $setting = new AiSetting;
        $setting->id = AiSetting::SINGLETON_ID;

        return $setting;
    }
}
