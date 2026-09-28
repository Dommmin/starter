<?php

use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function siteSettingsPayload(array $overrides = []): array
{
    return [
        'updated_at' => null,
        'site_name' => 'Acme Studio',
        'logo_media_id' => null,
        'og_image_media_id' => null,
        'contact_email' => 'hello@acme.test',
        'contact_phone' => '+48 123 456 789',
        'address_line' => 'Main Street 1',
        'postal_code' => '00-001',
        'city' => 'Warsaw',
        'country_code' => 'PL',
        'contact_recipient_email' => 'inbox@acme.test',
        'social_links' => [
            'linkedin' => 'https://www.linkedin.com/company/acme',
            'github' => '',
        ],
        'translations' => [
            'en' => [
                'tagline' => 'We build things',
                'footer_text' => 'Acme Studio. All rights reserved.',
                'seo_title' => 'Acme Studio — software house',
                'seo_description' => 'We design and build web applications.',
            ],
            'pl' => ['tagline' => 'Budujemy rzeczy', 'footer_text' => '', 'seo_title' => '', 'seo_description' => ''],
            'de' => ['tagline' => '', 'footer_text' => '', 'seo_title' => '', 'seo_description' => ''],
        ],
        ...$overrides,
    ];
}

test('an administrator saves the settings with an audit entry that hides the recipient', function () {
    $admin = User::factory()->admin()->create();
    $logo = MediaAsset::factory()->withVariants()->create();

    $this->actingAs($admin)
        ->put(route('admin.site-settings.update'), siteSettingsPayload(['logo_media_id' => $logo->id]))
        ->assertRedirect(route('admin.site-settings.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.siteSettings.updated')]);

    $settings = SiteSetting::query()->sole();
    expect($settings->id)->toBe(SiteSetting::SINGLETON_ID)
        ->and($settings->site_name)->toBe('Acme Studio')
        ->and($settings->logo_media_id)->toBe($logo->id)
        ->and($settings->contact_recipient_email)->toBe('inbox@acme.test')
        ->and($settings->social_links)->toBe(['linkedin' => 'https://www.linkedin.com/company/acme'])
        ->and($settings->updated_by)->toBe($admin->id)
        ->and($settings->updated_at)->not->toBeNull();

    expect(SiteSettingTranslation::query()->orderBy('locale')->pluck('locale')->all())->toBe(['en', 'pl']);

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::SiteSettingsUpdated)
        ->and($audit->actor_id)->toBe($admin->id)
        ->and($audit->changes['site_name']['new'])->toBe('Acme Studio')
        ->and($audit->changes['contact_email'])->toBe(['old' => null, 'new' => 'hello@acme.test'])
        ->and($audit->changes['en.seo_title']['new'])->toBe('Acme Studio — software house')
        ->and($audit->changes['contact_recipient_email'])->toBe(['redacted' => true]);

    expect(json_encode($audit->changes))->not->toContain('inbox@acme.test');
});

test('the edit form shows the fallback name before the first save and the saved values afterwards', function () {
    config(['seo.site_name' => 'Configured Name']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.site-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/site-settings/edit', false)
            ->where('settings.updatedAt', null)
            ->where('settings.siteName', 'Configured Name')
            ->where('settings.socialLinks.github', '')
            ->has('settings.translations', 3)
            ->has('socialNetworks', 7)
        );

    $this->actingAs($admin)->put(route('admin.site-settings.update'), siteSettingsPayload());

    $this->actingAs($admin)->get(route('admin.site-settings.edit'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('settings.siteName', 'Acme Studio')
            ->where('settings.contactRecipientEmail', 'inbox@acme.test')
            ->where('settings.socialLinks.linkedin', 'https://www.linkedin.com/company/acme')
            ->where('settings.translations.pl.tagline', 'Budujemy rzeczy')
            ->where('settings.translations.de.tagline', null)
            ->where('auth.can.manageSiteSettings', true)
        );
});

test('editors and users without a panel role cannot open or change the settings', function (string $role) {
    $user = $role === 'editor' ? User::factory()->editor()->create() : User::factory()->create();

    $this->actingAs($user)->get(route('admin.site-settings.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.site-settings.update'), siteSettingsPayload())->assertForbidden();

    expect(SiteSetting::query()->exists())->toBeFalse()
        ->and(AuditLog::query()->exists())->toBeFalse();
})->with(['editor', 'user']);

test('an editor does not see the site settings ability', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('auth.can.manageSiteSettings', false));
});

test('guests are redirected to the login page and change nothing', function () {
    $this->get(route('admin.site-settings.edit'))->assertRedirect(route('login'));
    $this->put(route('admin.site-settings.update'), siteSettingsPayload())->assertRedirect(route('login'));

    expect(SiteSetting::query()->exists())->toBeFalse()
        ->and(AuditLog::query()->exists())->toBeFalse();
});

test('a stale version is rejected as a conflict without changing the settings', function () {
    $admin = User::factory()->admin()->create();
    $settings = SiteSetting::factory()->create(['site_name' => 'Current']);
    $settings->forceFill(['updated_at' => Carbon::parse('2026-09-01 10:00:00')])->save();

    $this->actingAs($admin)
        ->put(route('admin.site-settings.update'), siteSettingsPayload(['updated_at' => '2026-08-01T10:00:00+00:00']))
        ->assertSessionHasErrors(['conflict' => __('admin.siteSettings.conflict')]);

    $this->actingAs($admin)
        ->put(route('admin.site-settings.update'), siteSettingsPayload(['updated_at' => null]))
        ->assertSessionHasErrors('conflict');

    expect($settings->refresh()->site_name)->toBe('Current')
        ->and(AuditLog::query()->exists())->toBeFalse();

    $this->actingAs($admin)
        ->put(route('admin.site-settings.update'), siteSettingsPayload(['updated_at' => '2026-09-01T10:00:00+00:00']))
        ->assertSessionHasNoErrors();

    expect($settings->refresh()->site_name)->toBe('Acme Studio');
});

test('invalid settings are rejected without saving anything', function (Closure $override, string $errorKey) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.site-settings.update'), siteSettingsPayload($override()))
        ->assertSessionHasErrors($errorKey);

    expect(SiteSetting::query()->exists())->toBeFalse()
        ->and(AuditLog::query()->exists())->toBeFalse();
})->with([
    'logo in quarantine' => [fn () => ['logo_media_id' => MediaAsset::factory()->create()->id], 'logo_media_id'],
    'rejected og image' => [fn () => ['og_image_media_id' => MediaAsset::factory()->rejected()->create()->id], 'og_image_media_id'],
    'clean pdf as logo' => [fn () => ['logo_media_id' => MediaAsset::factory()->clean()->pdf()->create()->id], 'logo_media_id'],
    'missing site name' => [fn () => ['site_name' => ''], 'site_name'],
    'invalid contact email' => [fn () => ['contact_email' => 'not-an-email'], 'contact_email'],
    'header injection in recipient' => [fn () => ['contact_recipient_email' => "inbox@acme.test\r\nBcc: x@evil.test"], 'contact_recipient_email'],
    'plain http social link' => [fn () => ['social_links' => ['facebook' => 'http://facebook.com/acme']], 'social_links.facebook'],
    'javascript social link' => [fn () => ['social_links' => ['x' => 'javascript:alert(1)']], 'social_links.x'],
    'network outside the allowlist' => [fn () => ['social_links' => ['myspace' => 'https://myspace.com/acme']], 'social_links'],
    'unknown locale' => [fn () => ['translations' => ['en' => ['tagline' => 'Hi'], 'fr' => ['tagline' => 'Salut']]], 'translations'],
    'too long seo title' => [fn () => ['translations' => ['en' => ['seo_title' => str_repeat('a', 71)]]], 'translations.en.seo_title'],
    'lowercase country code' => [fn () => ['country_code' => 'pl'], 'country_code'],
]);

test('the brand name can be changed alone from the console', function () {
    $settings = SiteSetting::factory()->create(['site_name' => 'Old', 'contact_email' => 'keep@acme.test']);
    SiteSettingTranslation::factory()->for($settings)->create(['tagline' => 'Kept']);

    app(UpdateSiteSettings::class)->updateSiteName('  New Brand  ');

    $settings->refresh();
    expect($settings->site_name)->toBe('New Brand')
        ->and($settings->contact_email)->toBe('keep@acme.test')
        ->and($settings->translations()->sole()->tagline)->toBe('Kept');

    $audit = AuditLog::query()->sole();
    expect($audit->actor_id)->toBeNull()
        ->and($audit->changes)->toBe(['site_name' => ['old' => 'Old', 'new' => 'New Brand']]);
});

test('the console creates the settings row when setting the name for the first time', function () {
    app(UpdateSiteSettings::class)->updateSiteName('First Brand');

    expect(SiteSetting::query()->sole()->site_name)->toBe('First Brand');
});

test('an invalid brand name from the console is rejected', function () {
    expect(fn () => app(UpdateSiteSettings::class)->updateSiteName("Bad\nName"))
        ->toThrow(ValidationException::class);

    expect(SiteSetting::query()->exists())->toBeFalse();
});
