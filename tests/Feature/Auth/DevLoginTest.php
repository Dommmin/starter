<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Load the dev-login route file the way web.php does (inside the `web`
 * group); the test run boots outside `local`, so it was skipped at boot.
 */
function loadDevLoginRoutes(): void
{
    Route::middleware('web')->group(base_path('routes/dev-login.php'));

    app('router')->getRoutes()->refreshNameLookups();
}

/**
 * Outside `testing` the CSRF middleware is active again; these tests cover
 * the route itself, not the token check.
 */
function registerLocalDevLogin(): void
{
    app()->detectEnvironment(fn (): string => 'local');
    test()->withoutMiddleware(PreventRequestForgery::class);

    loadDevLoginRoutes();
}

test('a guest signs in as the first administrator in the local environment', function () {
    registerLocalDevLogin();
    User::factory()->editor()->create();
    $administrator = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->post('/_dev/login-as-admin')->assertRedirect(route('admin.index'));

    $this->assertAuthenticatedAs($administrator);
});

test('the login page offers the dev login only in the local environment', function () {
    registerLocalDevLogin();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('devLoginUrl', route('dev.login')),
    );
});

test('the dev login does not exist outside the local environment', function () {
    User::factory()->admin()->create();

    expect(app()->environment('local'))->toBeFalse();

    $this->post('/_dev/login-as-admin')->assertNotFound();
    $this->assertGuest();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('devLoginUrl', null),
    );
});

test('the controller refuses to sign in outside local even when the route is loaded', function () {
    loadDevLoginRoutes();
    User::factory()->admin()->create();

    $this->post('/_dev/login-as-admin')->assertNotFound();
    $this->assertGuest();
});

test('the dev login fails without an administrator account', function () {
    registerLocalDevLogin();
    User::factory()->editor()->create();

    $this->post('/_dev/login-as-admin')->assertNotFound();
    $this->assertGuest();
});
