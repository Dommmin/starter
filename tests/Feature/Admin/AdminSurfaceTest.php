<?php

use App\Models\User;

/*
 * The admin theme is scoped by `data-surface="admin"` on <html>. It has to be
 * in the first HTML response so the body background and Radix portals (which
 * render into <body>) never flash the public palette.
 */

const ADMIN_SURFACE_ON_HTML = '/<html\b[^>]*\sdata-surface="admin"/';

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('admin panel pages render the admin surface on the html element', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk();

    expect($response->getContent())->toMatch(ADMIN_SURFACE_ON_HTML);
});

test('settings pages share the admin surface', function () {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    expect($response->getContent())->toMatch(ADMIN_SURFACE_ON_HTML);
});

test('public and authentication pages keep the public surface', function () {
    $response = $this->get(route('login'))->assertOk();

    expect($response->getContent())->not->toMatch(ADMIN_SURFACE_ON_HTML);
});
