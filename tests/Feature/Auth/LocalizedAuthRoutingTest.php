<?php

use App\Models\User;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('default auth screens render without prefix and with default locale', function () {
    $response = $this->get('/login');
    $response->assertOk();
    expect(app()->getLocale())->toBe('en');

    $response = $this->get('/register');
    $response->assertOk();
    expect(app()->getLocale())->toBe('en');

    $response = $this->get('/forgot-password');
    $response->assertOk();
    expect(app()->getLocale())->toBe('en');
});

test('default locale alias redirects with 301 to unprefixed auth route', function () {
    $response = $this->get('/en/login');
    $response->assertRedirect('/login');
    $response->assertStatus(301);

    $response = $this->get('/en/register');
    $response->assertRedirect('/register');
    $response->assertStatus(301);

    $response = $this->get('/en/forgot-password');
    $response->assertRedirect('/forgot-password');
    $response->assertStatus(301);
});

test('extra active public locale renders auth routes with prefix', function () {
    $response = $this->get('/pl/login');
    $response->assertOk();
    expect(app()->getLocale())->toBe('pl');

    $response = $this->get('/de/login');
    $response->assertOk();
    expect(app()->getLocale())->toBe('de');

    $response = $this->get('/pl/register');
    $response->assertOk();
    expect(app()->getLocale())->toBe('pl');

    $response = $this->get('/pl/forgot-password');
    $response->assertOk();
    expect(app()->getLocale())->toBe('pl');

    $response = $this->get('/pl/reset-password/sample-token');
    $response->assertOk();
    expect(app()->getLocale())->toBe('pl');
});

test('unknown or inactive locale on auth path returns 404', function () {
    $response = $this->get('/fr/login');
    $response->assertNotFound();

    $response = $this->get('/es/register');
    $response->assertNotFound();
});

test('prefixed admin path continues to return 404 without collision', function () {
    $response = $this->get('/pl/admin');
    $response->assertNotFound();

    $response = $this->get('/de/admin');
    $response->assertNotFound();
});

test('validation error on localized login post redirects back to localized login', function () {
    $user = User::factory()->create();

    $response = $this->from('/pl/login')->post('/pl/login', [
        'email' => $user->email,
        'password' => 'invalid-password',
    ]);

    $this->assertGuest();
    $response->assertRedirect('/pl/login');
    $response->assertSessionHasErrors('email');
});

test('users authenticating from localized login redirect to admin panel', function () {
    $user = User::factory()->create();

    $response = $this->post('/pl/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.index', absolute: false));
});

test('two factor challenge redirects to localized challenge route', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post('/pl/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/pl/two-factor-challenge');
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('unprefixed two factor challenge redirects to unprefixed route', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/two-factor-challenge');
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('alternate urls provider generates accurate alternate links for auth routes', function () {
    /** @var LocalizedUrlGenerator $generator */
    $generator = app(LocalizedUrlGenerator::class);

    $loginAlternates = $generator->getAlternateUrls('login');
    expect($loginAlternates)->toHaveKey('en')
        ->and($loginAlternates['en'])->toBe(url('/login'))
        ->and($loginAlternates['x-default'])->toBe(url('/login'))
        ->and($loginAlternates)->toHaveKey('pl')
        ->and($loginAlternates['pl'])->toBe(url('/pl/login'))
        ->and($loginAlternates)->toHaveKey('de')
        ->and($loginAlternates['de'])->toBe(url('/de/login'));

    $registerAlternates = $generator->getAlternateUrls('register');
    expect($registerAlternates['en'])->toBe(url('/register'))
        ->and($registerAlternates['pl'])->toBe(url('/pl/register'));

    $forgotAlternates = $generator->getAlternateUrls('password.request');
    expect($forgotAlternates['en'])->toBe(url('/forgot-password'))
        ->and($forgotAlternates['pl'])->toBe(url('/pl/forgot-password'));
});

test('users can logout from localized route and redirect to home', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/pl/logout');

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

test('rate limiting on localized login works as expected', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post('/pl/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
