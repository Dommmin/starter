<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('the command creates a verified administrator with the entered password and no invitation', function () {
    $this->artisan('app:create-user', ['email' => 'owner@example.com', '--name' => 'Owner', '--password' => true])
        ->expectsQuestion('Password', 'synthetic-Passw0rd!')
        ->expectsQuestion('Repeat the password', 'synthetic-Passw0rd!')
        ->expectsOutputToContain('created with the "admin" role')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@example.com')->sole();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('synthetic-Passw0rd!', $user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::UserCreated->value)->count())->toBe(1);

    Notification::assertNothingSent();
});

test('without a password the command sends the invitation', function () {
    $this->artisan('app:create-user', ['email' => 'editor@example.com', '--name' => 'Editor', '--role' => 'editor'])
        ->expectsOutputToContain('Invitation')
        ->assertSuccessful();

    $user = User::query()->where('email', 'editor@example.com')->sole();

    expect($user->role)->toBe(UserRole::Editor)
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, AccountInvitation::class);
});

test('the command refuses invalid input without creating an account', function (array $arguments, ?string $password, ?string $confirmation) {
    User::factory()->create(['email' => 'taken@example.com']);

    $command = $this->artisan('app:create-user', ['--name' => 'Someone', ...$arguments]);

    if ($password !== null) {
        $command->expectsQuestion('Password', $password)->expectsQuestion('Repeat the password', $confirmation);
    }

    $command->assertNotExitCode(0);

    expect(User::query()->count())->toBe(1);
    Notification::assertNothingSent();
})->with([
    'unknown role' => [['email' => 'new@example.com', '--role' => 'superuser'], null, null],
    'taken e-mail' => [['email' => 'taken@example.com'], null, null],
    'invalid e-mail' => [['email' => 'not-an-email'], null, null],
    'mismatched password' => [['email' => 'new@example.com', '--password' => true], 'synthetic-Passw0rd!', 'other-Passw0rd!'],
    'short password' => [['email' => 'new@example.com', '--password' => true], 'short', 'short'],
]);
