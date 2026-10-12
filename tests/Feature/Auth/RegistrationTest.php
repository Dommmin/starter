<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen passes the backend password rules to the form', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('passwordRules', Password::defaults()->toPasswordRulesString())
        );

    expect(Password::defaults()->toPasswordRulesString())->toContain('minlength: 8');
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Secure-Registration-Password-2026!',
        'password_confirmation' => 'Secure-Registration-Password-2026!',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticated();
    Notification::assertSentTo($user, VerifyEmail::class);
    expect($user->hasVerifiedEmail())->toBeFalse();
    $response->assertRedirect('/');
});

test('the sign-up flag is shared with the pages', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canRegister', true));
});
