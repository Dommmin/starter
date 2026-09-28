<?php

use App\Actions\Users\CreateUser;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('an account created from the console is audited without an actor', function () {
    $user = app(CreateUser::class)->handle(null, 'Owner', 'owner@example.test', UserRole::Admin);

    $entry = AuditLog::query()->where('action', AuditAction::UserCreated)->sole();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->subject_id)->toBe($user->id);
    Notification::assertSentTo($user, AccountInvitation::class);
});

test('an account created by an administrator records that administrator', function () {
    $actor = User::factory()->admin()->create();

    $user = app(CreateUser::class)->handle($actor, 'Editor', 'editor@example.test', UserRole::Editor);

    expect(AuditLog::query()->where('action', AuditAction::UserCreated)->sole()->actor_id)->toBe($actor->id)
        ->and($user->role)->toBe(UserRole::Editor);
});
