<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.index'));

    $response->assertRedirect(route('login'));
});

test('users without confirmed two factor authentication are redirected to security settings', function () {
    config(['fortify.require_two_factor_for_admin' => true]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.index'));

    $response->assertRedirect(route('security.edit'));
});

test('users without confirmed two factor authentication can visit the administration panel when it is not required', function () {
    config(['fortify.require_two_factor_for_admin' => false]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index'),
        );
});

test('users with confirmed two factor authentication can visit the administration panel', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->actingAs($user)->get(route('admin.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index'),
        );
});

test('admin start screen renders with authenticated user context', function () {
    $user = User::factory()->withTwoFactor()->create([
        'name' => 'Jan Kowalski',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->where('auth.user.name', 'Jan Kowalski')
            ->where('auth.user.email', $user->email)
            ->where('auth.user.two_factor_enabled', true)
            ->whereNot('auth.user.email_verified_at', null)
            ->has('system')
            ->where('system.appName', config('app.name'))
            ->has('security')
            ->where('security.requireTwoFactorForAdmin', (bool) config('fortify.require_two_factor_for_admin', false))
            ->has('modules')
            ->has('adminSettings')
            ->has('i18n.messages.admin.platformBadge')
            ->has('i18n.messages.admin.systemStatus')
            ->has('i18n.messages.admin.platformSettings')
        );
});

test('the home page is publicly accessible', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the dashboard route no longer exists', function () {
    $response = $this->get('/dashboard');

    $response->assertNotFound();
});
