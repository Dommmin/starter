<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('admin area defaults to english when user has no preference', function () {
    $user = User::factory()->create([
        'admin_locale' => null,
    ]);

    $response = $this->actingAs($user)->get('/settings/appearance');

    $response->assertOk();
    expect(app()->getLocale())->toBe('en');
});

test('admin area uses session locale for guests when valid', function () {
    $response = $this->withSession(['admin_locale' => 'pl'])->get('/login');

    $response->assertOk();
    expect(app()->getLocale())->toBe('pl');
});

test('admin area profile preference overrides session preference', function () {
    $user = User::factory()->create([
        'admin_locale' => 'en',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['admin_locale' => 'pl'])
        ->get('/settings/appearance');

    $response->assertOk();
    expect(app()->getLocale())->toBe('en');
});

test('admin area ignores inactive locale and falls back to en', function () {
    $user = User::factory()->create([
        'admin_locale' => 'xx-invalid',
    ]);

    $response = $this->actingAs($user)->get('/settings/appearance');

    $response->assertOk();
    expect(app()->getLocale())->toBe('en');
});

test('authenticated user can update admin locale preference', function () {
    $user = User::factory()->create([
        'admin_locale' => 'en',
    ]);

    $response = $this->actingAs($user)->patch('/settings/locale', [
        'locale' => 'pl',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->admin_locale)->toBe('pl');
    expect(session('admin_locale'))->toBe('pl');
});

test('guest can update admin locale in session', function () {
    $response = $this->patch('/settings/locale', [
        'locale' => 'pl',
    ]);

    $response->assertRedirect();
    expect(session('admin_locale'))->toBe('pl');
});

test('cannot update admin locale with unsupported code', function () {
    $user = User::factory()->create([
        'admin_locale' => 'en',
    ]);

    $response = $this->actingAs($user)->patch('/settings/locale', [
        'locale' => 'xx',
    ]);

    $response->assertSessionHasErrors('locale');
    expect($user->fresh()->admin_locale)->toBe('en');
});

test('logout clears admin locale from session', function () {
    $user = User::factory()->create([
        'admin_locale' => 'pl',
    ]);

    $this->actingAs($user)->withSession(['admin_locale' => 'pl']);

    $response = $this->post('/logout');

    $response->assertRedirect();
    expect(session()->has('admin_locale'))->toBeFalse();
});

test('public url is not overridden by admin profile or session preference', function () {
    $user = User::factory()->create([
        'admin_locale' => 'pl',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['admin_locale' => 'pl'])
        ->get('/');

    $response->assertOk();
    // Default public is 'en'
    expect(app()->getLocale())->toBe('en');
});

test('patching admin locale updates preference and reflects in subsequent admin page props', function () {
    config(['fortify.require_two_factor_for_admin' => false]);

    $user = User::factory()->admin()->create([
        'admin_locale' => 'en',
    ]);

    $patchResponse = $this->actingAs($user)
        ->from('/admin')
        ->patch('/settings/locale', [
            'locale' => 'pl',
        ]);

    $patchResponse->assertRedirect('/admin');
    expect($user->fresh()->admin_locale)->toBe('pl');

    $adminResponse = $this->actingAs($user->fresh())
        ->get('/admin');

    $adminResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('locale', 'pl')
        ->where('i18n.locale', 'pl')
        ->where('i18n.messages.admin.dashboard', 'Pulpit')
    );
});
