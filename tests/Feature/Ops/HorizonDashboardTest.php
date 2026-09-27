<?php

use App\Models\User;

test('guests are redirected to the login page instead of the Horizon dashboard', function () {
    $this->get('/horizon')->assertRedirect(route('login'));

    $this->getJson('/horizon/api/stats')->assertUnauthorized();
});

test('editors and users without a panel role cannot open Horizon', function (string $state) {
    $user = $state === 'none' ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/horizon')
        ->assertForbidden()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->actingAs($user)->getJson('/horizon/api/stats')->assertForbidden();
})->with(['editor', 'none']);

test('administrators can open the Horizon dashboard, which is not indexable', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/horizon')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('administrators without confirmed MFA are sent to the security settings when MFA is required', function () {
    config(['fortify.require_two_factor_for_admin' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/horizon')
        ->assertRedirect(route('security.edit'));
});
