<?php

use App\Services\Localization\LocalizedUrlGenerator;

test('default public url has no prefix and renders default locale', function () {
    $defaultLocale = config('localization.public_default');
    $response = $this->get('/');

    $response->assertOk();
    expect(app()->getLocale())->toBe($defaultLocale);
});

test('default locale alias redirects with 301 to root', function () {
    $defaultLocale = config('localization.public_default');
    $response = $this->get("/{$defaultLocale}");

    $response->assertRedirect('/');
    $response->assertStatus(301);
});

test('extra active public locale renders under prefixed url', function () {
    $defaultLocale = config('localization.public_default');
    $extraLocale = $defaultLocale === 'en' ? 'pl' : 'en';
    $response = $this->get("/{$extraLocale}");

    $response->assertOk();
    expect(app()->getLocale())->toBe($extraLocale);
});

test('unknown or inactive public locale prefix returns 404', function () {
    $response = $this->get('/fr');

    $response->assertNotFound();
});

test('prefixed admin path returns 404 without collision', function () {
    $defaultLocale = config('localization.public_default');
    $prefix = $defaultLocale === 'de' ? 'pl' : 'de';
    $response = $this->get("/{$prefix}/admin");

    $response->assertNotFound();
});

test('alternate urls provider returns correct canonical map', function () {
    /** @var LocalizedUrlGenerator $generator */
    $generator = app(LocalizedUrlGenerator::class);
    $defaultLocale = config('localization.public_default');
    $extraLocale = $defaultLocale === 'en' ? 'pl' : 'en';
    $alternates = $generator->getAlternateUrls('home');

    expect($alternates)->toHaveKey($defaultLocale)
        ->and($alternates[$defaultLocale])->toBe(url('/'))
        ->and($alternates['x-default'])->toBe(url('/'))
        ->and($alternates)->toHaveKey($extraLocale)
        ->and($alternates[$extraLocale])->toBe(url("/{$extraLocale}"));
});

test('url generator safely falls back to default locale when target locale is not active publicly', function () {
    /** @var LocalizedUrlGenerator $generator */
    $generator = app(LocalizedUrlGenerator::class);

    $url = $generator->url('home', [], 'fr');
    expect($url)->toBe(url('/'));

    $resetUrl = $generator->url('password.reset', ['token' => 'sample-token'], 'fr');
    expect($resetUrl)->toBe(route('password.reset', ['token' => 'sample-token']));
});
