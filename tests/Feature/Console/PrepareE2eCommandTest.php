<?php

use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config([
        'e2e.password' => 'synthetic-e2e-password',
        'fortify.require_two_factor_for_admin' => false,
    ]);
});

test('the command prepares verified synthetic admin and editor accounts without 2FA', function () {
    User::factory()->withTwoFactor()->create([
        'email' => 'e2e-editor@example.test',
        'password' => 'previous-password',
    ]);

    $this->artisan('app:e2e-prepare')->assertSuccessful();

    $admin = User::query()->where('email', 'e2e-admin@example.test')->sole();
    $editor = User::query()->where('email', 'e2e-editor@example.test')->sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($editor->role)->toBe(UserRole::Editor)
        ->and(Hash::check('synthetic-e2e-password', $admin->password))->toBeTrue()
        ->and(Hash::check('synthetic-e2e-password', $editor->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($editor->two_factor_confirmed_at)->toBeNull()
        ->and($editor->two_factor_secret)->toBeNull();
});

test('the command removes only pages and contact messages of previous E2E runs', function () {
    $e2ePage = Page::factory()
        ->has(PageTranslation::factory()->published()->state(['slug' => 'e2e-previous-run']), 'translations')
        ->create();
    $contentPage = Page::factory()
        ->has(PageTranslation::factory()->published()->state(['slug' => 'privacy-policy']), 'translations')
        ->create();
    ContactMessage::factory()->create(['email' => 'e2e-contact-1@example.test']);
    $visitorMessage = ContactMessage::factory()->create(['email' => 'visitor@example.com']);

    $this->artisan('app:e2e-prepare')->assertSuccessful();

    expect(Page::query()->whereKey($e2ePage->id)->exists())->toBeFalse()
        ->and(Page::query()->whereKey($contentPage->id)->exists())->toBeTrue()
        ->and(ContactMessage::query()->pluck('id')->all())->toBe([$visitorMessage->id]);
});

test('the command resets the contact rate limit of the browser host', function () {
    $key = md5('contact'.'contact-ip|127.0.0.1');
    RateLimiter::hit($key, 3600);

    $this->artisan('app:e2e-prepare', ['--client-host' => 'localhost'])->assertSuccessful();

    expect(RateLimiter::attempts($key))->toBe(0);
});

test('the command refuses to run outside local and testing', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $this->artisan('app:e2e-prepare')
        ->expectsOutputToContain('only in the local or testing environment')
        ->assertFailed();

    expect(User::query()->where('email', 'like', 'e2e-%')->exists())->toBeFalse();
})->with(['production', 'staging']);

test('the command refuses a missing or short password and required admin 2FA', function (array $config, string $message) {
    config($config);

    $this->artisan('app:e2e-prepare')
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(User::query()->where('email', 'like', 'e2e-%')->exists())->toBeFalse();
})->with([
    'missing password' => [['e2e.password' => null], 'Set E2E_PASSWORD'],
    'short password' => [['e2e.password' => 'short'], 'Set E2E_PASSWORD'],
    'admin 2FA required' => [['fortify.require_two_factor_for_admin' => true], 'ADMIN_REQUIRE_TWO_FACTOR=false'],
]);
