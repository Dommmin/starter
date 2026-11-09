<?php

use App\Enums\AiProvider;
use App\Enums\AssistantFeature;
use App\Enums\AuditAction;
use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'fortify.require_two_factor_for_admin' => false,
        'assistant.enabled' => true,
        'assistant.features.seo_meta' => true,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => null,
        'ai.providers.openai.key' => null,
    ]);
});

/**
 * An administrator with a recently confirmed password.
 */
function aiSettingsAdmin(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

const SYNTHETIC_AI_KEY = 'synthetic-panel-key';

test('an administrator saves the provider and key, stored encrypted and audited without the key', function () {
    aiSettingsAdmin();

    $this->put(route('admin.system.ai.update'), ['provider' => 'openai', 'api_key' => SYNTHETIC_AI_KEY])
        ->assertRedirect(route('admin.system.index'))
        ->assertSessionHasNoErrors();

    $setting = AiSetting::query()->sole();
    expect($setting->provider)->toBe(AiProvider::OpenAi)
        ->and($setting->api_key)->toBe(SYNTHETIC_AI_KEY);

    $raw = DB::table('ai_settings')->value('api_key');
    expect($raw)->not->toContain(SYNTHETIC_AI_KEY);

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::AiSettingsUpdated)
        ->and($audit->changes)->toEqual([
            'provider' => ['old' => null, 'new' => 'openai'],
            'api_key' => ['redacted' => true],
        ])
        ->and(json_encode($audit->changes))->not->toContain(SYNTHETIC_AI_KEY);
});

test('an empty key keeps the stored one when the provider does not change', function () {
    $admin = aiSettingsAdmin();
    AiSetting::factory()->create(['api_key' => SYNTHETIC_AI_KEY]);

    $this->put(route('admin.system.ai.update'), ['provider' => 'openai', 'api_key' => ''])
        ->assertSessionHasNoErrors();

    $setting = AiSetting::query()->sole();
    expect($setting->api_key)->toBe(SYNTHETIC_AI_KEY)
        ->and($setting->updated_by)->toBe($admin->id);
});

test('a key is required when none is stored or the provider changes', function () {
    aiSettingsAdmin();

    $this->put(route('admin.system.ai.update'), ['provider' => 'openai', 'api_key' => ''])
        ->assertSessionHasErrors('api_key');
    expect(AiSetting::query()->exists())->toBeFalse();

    AiSetting::factory()->create(['api_key' => SYNTHETIC_AI_KEY]);

    $this->put(route('admin.system.ai.update'), ['provider' => 'gemini', 'api_key' => ''])
        ->assertSessionHasErrors('api_key');
    expect(AiSetting::query()->sole()->provider)->toBe(AiProvider::OpenAi);
});

test('an unknown provider is rejected and the key is not flashed to the session', function () {
    aiSettingsAdmin();

    $this->put(route('admin.system.ai.update'), ['provider' => 'cohere', 'api_key' => SYNTHETIC_AI_KEY])
        ->assertSessionHasErrors('provider')
        ->assertSessionMissing('_old_input.api_key');

    expect(AiSetting::query()->exists())->toBeFalse();
});

test('saving and removing require a recently confirmed password', function () {
    $admin = User::factory()->admin()->create();
    AiSetting::factory()->create(['api_key' => SYNTHETIC_AI_KEY]);

    $this->actingAs($admin)
        ->put(route('admin.system.ai.update'), ['provider' => 'gemini', 'api_key' => 'synthetic-other-key'], ['X-Inertia' => 'true'])
        ->assertStatus(423);
    $this->actingAs($admin)
        ->delete(route('admin.system.ai.destroy'), [], ['X-Inertia' => 'true'])
        ->assertStatus(423);

    $setting = AiSetting::query()->sole();
    expect($setting->provider)->toBe(AiProvider::OpenAi)
        ->and($setting->api_key)->toBe(SYNTHETIC_AI_KEY);
});

test('editors cannot see, save or remove the AI settings', function () {
    $editor = User::factory()->editor()->create();
    AiSetting::factory()->create(['api_key' => SYNTHETIC_AI_KEY]);
    $this->actingAs($editor)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('admin.system.index'))->assertForbidden();
    $this->put(route('admin.system.ai.update'), ['provider' => 'gemini', 'api_key' => 'synthetic-other-key'])
        ->assertForbidden();
    $this->delete(route('admin.system.ai.destroy'))->assertForbidden();

    $setting = AiSetting::query()->sole();
    expect($setting->provider)->toBe(AiProvider::OpenAi)
        ->and($setting->api_key)->toBe(SYNTHETIC_AI_KEY);
    expect(AuditLog::query()->exists())->toBeFalse();
});

test('the System page tells where the key comes from and never sends it', function () {
    aiSettingsAdmin();
    AiSetting::factory()->create(['api_key' => SYNTHETIC_AI_KEY]);

    $response = $this->get(route('admin.system.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ai', [
                'provider' => 'openai',
                'hasPanelKey' => true,
                'environmentConfigured' => false,
                'assistantEnabled' => true,
            ]));

    expect($response->getContent())->not->toContain(SYNTHETIC_AI_KEY);
});

test('the panel key takes precedence over the environment and removing it restores the fallback', function () {
    config(['ai.providers.anthropic.key' => 'synthetic-environment-key']);
    AiSetting::factory()->create(['api_key' => SYNTHETIC_AI_KEY]);

    expect(AssistantFeature::SeoMeta->isAvailable())->toBeTrue()
        ->and(config('ai.default'))->toBe('openai')
        ->and(config('ai.providers.openai.key'))->toBe(SYNTHETIC_AI_KEY);

    aiSettingsAdmin();
    $this->delete(route('admin.system.ai.destroy'))
        ->assertRedirect(route('admin.system.index'));

    $setting = AiSetting::query()->sole();
    expect($setting->provider)->toBeNull()
        ->and($setting->api_key)->toBeNull();
    expect(AuditLog::query()->sole()->changes)->toEqual([
        'provider' => ['old' => 'openai', 'new' => null],
        'api_key' => ['redacted' => true],
    ]);

    expect(AssistantFeature::SeoMeta->isAvailable())->toBeTrue()
        ->and(config('ai.default'))->toBe('anthropic')
        ->and(config('ai.providers.openai.key'))->toBeNull();
});

test('a panel key makes the assistant available without an environment key', function () {
    expect(AssistantFeature::SeoMeta->isAvailable())->toBeFalse();

    AiSetting::factory()->create(['provider' => AiProvider::Gemini, 'api_key' => SYNTHETIC_AI_KEY]);

    expect(AssistantFeature::SeoMeta->isAvailable())->toBeTrue()
        ->and(config('ai.default'))->toBe('gemini');
});
