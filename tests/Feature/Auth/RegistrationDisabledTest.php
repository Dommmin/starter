<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Boots the application with self-service sign-up switched off, as
 * `app:init-project` configures every new client project by default.
 */
beforeEach(function () {
    putenv('APP_REGISTRATION_ENABLED=false');
    $_ENV['APP_REGISTRATION_ENABLED'] = $_SERVER['APP_REGISTRATION_ENABLED'] = 'false';

    // The in-memory test database outlives the application: close the test
    // transaction of the old instance before RefreshDatabase opens one again.
    $this->app['db']->connection()->rollBack();
    $this->refreshApplication();
    $this->refreshDatabase();
    // The fresh application has no Vite fake from TestCase::setUp(): without
    // it pages need public/build, which the database CI jobs never build.
    $this->withoutVite();
});

afterEach(function () {
    putenv('APP_REGISTRATION_ENABLED');
    unset($_ENV['APP_REGISTRATION_ENABLED'], $_SERVER['APP_REGISTRATION_ENABLED']);
});

test('the registration screen is not reachable', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/register', '/de/register']);

test('a sign-up request is rejected without creating an account', function (string $path) {
    // Only the GET-only public page route `/{slug}` matches the path, hence 405.
    $this->post($path, [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Secure-Registration-Password-2026!',
        'password_confirmation' => 'Secure-Registration-Password-2026!',
    ])->assertMethodNotAllowed();

    foreach (['register', 'register.store', 'localized.register', 'localized.register.store'] as $routeName) {
        expect(Route::has($routeName))->toBeFalse();
    }
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
})->with(['/register', '/de/register']);

test('no sign-up url is shared with the pages', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.registerUrl', null));
});
