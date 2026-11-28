<?php

use App\Actions\Redirects\RecordNotFoundHit;
use App\Models\NotFoundLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('admin can save marketing integrations and chat widget in site settings', function () {
    $admin = User::factory()->admin()->create();
    $setting = SiteSetting::factory()->create(['id' => SiteSetting::SINGLETON_ID]);
    Cache::forget(SiteSettingsRepository::CACHE_KEY);

    $response = $this->actingAs($admin)->put(route('admin.site-settings.update'), [
        'updated_at' => $setting->updated_at->toIso8601String(),
        'site_name' => 'Starter',
        'default_theme' => 'system',
        'contact_auto_reply' => false,
        'announcement_enabled' => false,
        'announcement_tone' => 'info',
        'ga4_id' => 'G-TEST123456',
        'gtm_id' => 'GTM-ABC1234',
        'meta_pixel_id' => '123456789012345',
        'chat_provider' => 'crisp',
        'chat_widget_id' => 'widget-abc-123',
        'translations' => [
            config('app.fallback_locale', 'pl') => [
                'tagline' => 'Tagline',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.site-settings.edit'));

    $updated = SiteSetting::query()->find(SiteSetting::SINGLETON_ID);
    expect($updated)->not->toBeNull();
    expect($updated->ga4_id)->toBe('G-TEST123456');
    expect($updated->gtm_id)->toBe('GTM-ABC1234');
    expect($updated->meta_pixel_id)->toBe('123456789012345');
    expect($updated->chat_provider)->toBe('crisp');
    expect($updated->chat_widget_id)->toBe('widget-abc-123');
});

test('site settings reject invalid marketing integration identifiers', function () {
    $admin = User::factory()->admin()->create();
    $setting = SiteSetting::factory()->create(['id' => SiteSetting::SINGLETON_ID]);
    Cache::forget(SiteSettingsRepository::CACHE_KEY);

    $response = $this->actingAs($admin)->put(route('admin.site-settings.update'), [
        'updated_at' => $setting->updated_at->toIso8601String(),
        'site_name' => 'Starter',
        'default_theme' => 'system',
        'contact_auto_reply' => false,
        'announcement_enabled' => false,
        'announcement_tone' => 'info',
        'ga4_id' => 'INVALID-ID',
        'gtm_id' => 'INVALID-GTM',
        'meta_pixel_id' => 'not-numeric',
        'chat_provider' => 'unknown',
        'translations' => [
            config('app.fallback_locale', 'pl') => [
                'tagline' => 'Tagline',
            ],
        ],
    ]);

    $response->assertSessionHasErrors([
        'ga4_id',
        'gtm_id',
        'meta_pixel_id',
        'chat_provider',
    ]);
});

test('public response shares consent integrations and extends csp origins', function () {
    SiteSetting::factory()->create([
        'id' => SiteSetting::SINGLETON_ID,
        'ga4_id' => 'G-CSP123456',
        'meta_pixel_id' => '987654321098765',
        'chat_provider' => 'crisp',
        'chat_widget_id' => 'crisp-site-1',
    ]);
    Cache::forget(SiteSettingsRepository::CACHE_KEY);

    $response = $this->get('/');

    $response->assertOk();
    $csp = (string) ($response->headers->get('Content-Security-Policy')
        ?? $response->headers->get('Content-Security-Policy-Report-Only'));
    expect($csp)->toContain('https://www.googletagmanager.com');
    expect($csp)->toContain('https://connect.facebook.net');
    expect($csp)->toContain('https://client.crisp.chat');
});

test('404 logger records meaningful paths, ignores asset noise, and admin can view or dismiss', function () {
    $recorder = app(RecordNotFoundHit::class);
    $recorder->handle(Request::create('/old-offer', 'GET', server: ['HTTP_REFERER' => 'https://google.com']));
    $recorder->handle(Request::create('/old-offer', 'GET', server: ['HTTP_REFERER' => 'https://bing.com']));
    $recorder->handle(Request::create('/wp-login.php', 'GET'));
    $recorder->handle(Request::create('/favicon.ico', 'GET'));
    $recorder->handle(Request::create('/images/missing.png', 'GET'));

    expect(NotFoundLog::query()->count())->toBe(1);

    $log = NotFoundLog::query()->first();
    expect($log?->path)->toBe('/old-offer');
    expect($log?->hits)->toBe(2);
    expect($log?->last_referer)->toBe('https://bing.com');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.redirects.not-found.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/redirects/not-found')
            ->has('items', 1)
            ->where('items.0.path', '/old-offer'));

    $this->actingAs($admin)
        ->get(route('admin.redirects.create', ['source_path' => '/old-offer']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('redirect.sourcePath', '/old-offer'));

    $this->actingAs($admin)
        ->delete(route('admin.redirects.not-found.destroy', $log))
        ->assertRedirect();

    expect(NotFoundLog::query()->count())->toBe(0);
});

test('a configured chat widget alone makes the site ask for marketing consent', function () {
    SiteSetting::factory()->create([
        'id' => SiteSetting::SINGLETON_ID,
        'chat_provider' => 'crisp',
        'chat_widget_id' => 'crisp-site-1',
    ]);
    Cache::forget(SiteSettingsRepository::CACHE_KEY);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('consent.categories', ['marketing']));
});

test('404 logger stores the referer without query string or fragment', function () {
    app(RecordNotFoundHit::class)->handle(Request::create('/old-offer', 'GET', server: [
        'HTTP_REFERER' => 'https://example.com/search?email=ada@example.com#token',
    ]));

    expect(NotFoundLog::query()->sole()->last_referer)->toBe('https://example.com/search');
});

test('404 logger stops adding new paths at the limit but still counts known ones', function () {
    NotFoundLog::query()->insert(array_map(
        fn (int $i): array => ['path' => "/missing-{$i}", 'hits' => 1, 'last_seen_at' => now()],
        range(1, RecordNotFoundHit::MAX_PATHS),
    ));

    $recorder = app(RecordNotFoundHit::class);
    $recorder->handle(Request::create('/one-too-many', 'GET'));
    $recorder->handle(Request::create('/missing-1', 'GET'));

    expect(NotFoundLog::query()->count())->toBe(RecordNotFoundHit::MAX_PATHS)
        ->and(NotFoundLog::query()->where('path', '/missing-1')->value('hits'))->toBe(2);
});
