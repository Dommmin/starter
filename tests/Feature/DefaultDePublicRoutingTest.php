<?php

use App\Models\User;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config([
        'localization.registry.de' => [
            'code' => 'de',
            'name' => 'German',
            'native' => 'Deutsch',
            'dir' => 'ltr',
        ],
        'localization.registry.en' => [
            'code' => 'en',
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
        ],
        'localization.registry.pl' => [
            'code' => 'pl',
            'name' => 'Polish',
            'native' => 'Polski',
            'dir' => 'ltr',
        ],
        'localization.public_locales' => ['de', 'en', 'pl'],
        'localization.public_default' => 'de',
        'localization.public_fallback' => 'de',
        'localization.admin_locales' => ['en', 'de'],
        'localization.admin_default' => 'en',
        'localization.admin_fallback' => 'en',
    ]);

    // Rebind LocalizationConfig to pick up modified config
    app()->forgetInstance(LocalizationConfig::class);
    app()->forgetInstance(LocalizationManager::class);
    app()->forgetInstance(LocalizedUrlGenerator::class);
});

test('public default DE renders root without prefix in German', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect(app()->getLocale())->toBe('de');
});

test('public default DE renders login screen without prefix in German', function () {
    $response = $this->get('/login');

    $response->assertOk();
    expect(app()->getLocale())->toBe('de');
});

test('public default DE handles failed login POST and redirects back to /login with German context', function () {
    $user = User::factory()->create();

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    expect(app()->getLocale())->toBe('de');
});

test('public default DE renders forgot password screen in German', function () {
    $response = $this->get('/forgot-password');

    $response->assertOk();
    expect(app()->getLocale())->toBe('de');
});

test('public default DE renders reset password screen in German', function () {
    $response = $this->get('/reset-password/sample-token');

    $response->assertOk();
    expect(app()->getLocale())->toBe('de');
});

test('public default DE generates accurate alternate urls for home and the front.php articles route', function () {
    // 'articles.index' is a route actually registered in routes/front.php (not one
    // registered ad hoc inside this test), so this exercises the real
    // default+prefix wiring from routes/web.php for every route defined in
    // front.php, not just 'home'. Route *structure* (which locale is
    // unprefixed) is fixed once at boot from the real .env config, so it
    // still reflects the actual default locale here — only the alternate
    // URLs below are generated from this test's overridden ['de'] default.
    expect(Route::has('articles.index'))->toBeTrue()
        ->and(Route::has('localized.articles.index'))->toBeTrue();

    /** @var LocalizedUrlGenerator $generator */
    $generator = app(LocalizedUrlGenerator::class);

    // Test alternate urls for home
    $homeAlternates = $generator->getAlternateUrls('home');
    expect($homeAlternates)->toHaveKey('de')
        ->and($homeAlternates['de'])->toBe(url('/'))
        ->and($homeAlternates['x-default'])->toBe(url('/'))
        ->and($homeAlternates)->toHaveKey('en')
        ->and($homeAlternates['en'])->toBe(url('/en'))
        ->and($homeAlternates)->toHaveKey('pl')
        ->and($homeAlternates['pl'])->toBe(url('/pl'));

    // Test alternate urls for the front route 'articles.index'
    $articlesAlternates = $generator->getAlternateUrls('articles.index');
    expect($articlesAlternates)->toHaveKey('de')
        ->and($articlesAlternates['de'])->toBe(url('/articles'))
        ->and($articlesAlternates['x-default'])->toBe(url('/articles'))
        ->and($articlesAlternates)->toHaveKey('en')
        ->and($articlesAlternates['en'])->toBe(url('/en/articles'))
        ->and($articlesAlternates)->toHaveKey('pl')
        ->and($articlesAlternates['pl'])->toBe(url('/pl/articles'));
});

test('public default DE generates proper URLs in German context', function () {
    $this->get('/');
    expect(app()->getLocale())->toBe('de');

    $generator = app(LocalizedUrlGenerator::class);
    expect($generator->url('home'))->toBe(url('/'));
    expect($generator->url('home', [], 'de'))->toBe(url('/'));
    expect($generator->url('home', [], 'en'))->toBe(url('/en'));
    expect($generator->url('login'))->toBe(route('login'));
});
