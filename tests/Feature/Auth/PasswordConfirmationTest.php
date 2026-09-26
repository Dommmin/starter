<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/confirm-password'),
    );
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

test('password confirmation keeps the user on the current screen when the password is incorrect', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post(route('password.confirm'), [
            'password' => 'incorrect-password',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
});

test('password confirmation marks the current session as confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post(route('password.confirm'), [
            'password' => 'password',
        ]);

    $response->assertCreated();

    expect(session()->has('auth.password_confirmed_at'))->toBeTrue();
});
