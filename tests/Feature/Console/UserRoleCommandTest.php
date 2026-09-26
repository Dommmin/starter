<?php

use App\Enums\UserRole;
use App\Models\User;

test('the command assigns a role to the user', function (string $role, UserRole $expected) {
    $user = User::factory()->create();

    $this->artisan('app:user-role', ['email' => $user->email, 'role' => $role])
        ->expectsOutputToContain($expected->value)
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe($expected);
})->with([
    'admin' => ['admin', UserRole::Admin],
    'editor' => ['editor', UserRole::Editor],
]);

test('the command revokes the role when none or no role is given', function (array $arguments) {
    $user = User::factory()->admin()->create();

    $this->artisan('app:user-role', ['email' => $user->email, ...$arguments])
        ->expectsOutputToContain('revoked')
        ->assertSuccessful();

    expect($user->refresh()->role)->toBeNull();
})->with([
    'explicit none' => [['role' => 'none']],
    'omitted role' => [[]],
]);

test('the command rejects an unknown role without changing the user', function () {
    $user = User::factory()->editor()->create();

    $this->artisan('app:user-role', ['email' => $user->email, 'role' => 'superuser'])
        ->expectsOutputToContain('Unknown role')
        ->assertFailed();

    expect($user->refresh()->role)->toBe(UserRole::Editor);
});

test('the command rejects an unknown e-mail address', function () {
    $this->artisan('app:user-role', ['email' => 'missing@example.com', 'role' => 'admin'])
        ->expectsOutputToContain('No user found')
        ->assertFailed();

    expect(User::query()->whereNotNull('role')->exists())->toBeFalse();
});
