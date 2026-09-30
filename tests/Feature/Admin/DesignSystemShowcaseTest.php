<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Load the showcase route file the way web.php does (inside the `web`
 * group); the test run boots outside `local`, so it was skipped at boot.
 */
function loadDesignSystemShowcaseRoutes(): void
{
    Route::middleware('web')->group(base_path('routes/design-system.php'));

    app('router')->getRoutes()->refreshNameLookups();
}

function registerLocalDesignSystemShowcase(): void
{
    app()->detectEnvironment(fn (): string => 'local');

    loadDesignSystemShowcaseRoutes();
}

test('an admin opens the showcase in the local environment', function () {
    registerLocalDesignSystemShowcase();
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get('/admin/design-system')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/design-system/index')
            ->where('designSystemUrl', '/admin/design-system')
            ->has('i18n.messages.admin.designSystem.title'),
        );
});

test('the showcase does not exist outside the local environment', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    expect(app()->environment('local'))->toBeFalse();

    $this->actingAs($admin)->get('/admin/design-system')->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('designSystemUrl'));
});

test('the showcase controller refuses to render outside local even when the route is loaded', function () {
    loadDesignSystemShowcaseRoutes();
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $this->actingAs($admin)->get('/admin/design-system')->assertNotFound();
});

test('guests are redirected to the login page', function () {
    registerLocalDesignSystemShowcase();

    $this->get('/admin/design-system')->assertRedirect(route('login'));
});

test('unverified accounts are sent to email verification', function () {
    registerLocalDesignSystemShowcase();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/admin/design-system')->assertRedirect(route('verification.notice'));
});

/*
| In `local`, User::canAccessAdminPanel() grants the panel to every account,
| so the panel guard is exercised with the route loaded outside `local`.
*/
test('accounts without panel access are forbidden by the panel guard', function () {
    loadDesignSystemShowcaseRoutes();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/design-system')->assertForbidden();
});
