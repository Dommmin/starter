<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    Notification::fake();
});

/**
 * Act as the given user with a recently confirmed password.
 */
function confirmedAs(User $user): TestCase
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

test('editors and users without a role cannot manage accounts and change nothing', function (User $actor) {
    $target = User::factory()->editor()->create(['name' => 'Target']);
    $before = User::query()->count();

    confirmedAs($actor)->get(route('admin.users.create'))->assertForbidden();
    confirmedAs($actor)->post(route('admin.users.store'), [
        'name' => 'Intruder', 'email' => 'intruder@example.com', 'role' => 'admin',
    ])->assertForbidden();
    confirmedAs($actor)->get(route('admin.users.edit', $target))->assertForbidden();
    confirmedAs($actor)->put(route('admin.users.update', $target), [
        'updated_at' => $target->updated_at->toIso8601String(),
        'name' => 'Changed', 'email' => $target->email, 'role' => 'admin',
    ])->assertForbidden();
    confirmedAs($actor)->delete(route('admin.users.destroy', $target))->assertForbidden();

    expect(User::query()->count())->toBe($before)
        ->and($target->refresh()->name)->toBe('Target')
        ->and($target->role)->toBe(UserRole::Editor)
        ->and(AuditLog::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'editor' => fn () => User::factory()->editor()->create(),
    'no role' => fn () => User::factory()->create(),
]);

test('mutations require a recently confirmed password', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('admin.users.store'), ['name' => 'New', 'email' => 'new@example.com', 'role' => ''])
        ->assertStatus(423)
        ->assertHeader('X-Password-Confirmation-Required', 'true');

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

test('the list exposes the role, marks the own account and allows creating', function () {
    $admin = User::factory()->admin()->create(['name' => 'Alice']);
    User::factory()->editor()->create(['name' => 'Bob']);

    $this->actingAs($admin)->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('items.0.name', 'Alice')
            ->where('items.0.role', 'admin')
            ->where('items.0.isSelf', true)
            ->where('items.1.role', 'editor')
            ->where('items.1.isSelf', false)
            ->where('can.create', true)
        );
});

test('an administrator creates an editor who receives a set-password invitation', function () {
    $admin = User::factory()->admin()->create();

    $response = confirmedAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Editor',
        'email' => 'editor@example.com',
        'role' => 'editor',
    ]);

    $user = User::query()->where('email', 'editor@example.com')->sole();
    $response->assertRedirect(route('admin.users.edit', $user));

    expect($user->role)->toBe(UserRole::Editor)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->not->toBeEmpty();

    Notification::assertSentTo($user, AccountInvitation::class, function (AccountInvitation $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $query = parse_url((string) $mail->actionUrl, PHP_URL_QUERY);
        parse_str((string) $query, $params);
        $token = basename((string) parse_url((string) $mail->actionUrl, PHP_URL_PATH));

        return str_contains((string) $mail->actionUrl, '/reset-password/')
            && ($params['email'] ?? null) === 'editor@example.com'
            && Password::broker()->tokenExists($user, $token);
    });

    $audit = AuditLog::query()->where('action', AuditAction::UserCreated->value)->sole();
    expect($audit->actor_id)->toBe($admin->id)
        ->and($audit->changes['role'])->toBe(['old' => null, 'new' => 'editor'])
        ->and($audit->changes['email'])->toBe(['redacted' => true]);
});

test('an account without a role can be created and duplicates or unknown roles are rejected', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    confirmedAs($admin)->post(route('admin.users.store'), [
        'name' => 'Reader', 'email' => 'reader@example.com', 'role' => '',
    ])->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'reader@example.com')->sole()->role)->toBeNull();

    confirmedAs($admin)->post(route('admin.users.store'), [
        'name' => 'Dup', 'email' => 'taken@example.com', 'role' => '',
    ])->assertSessionHasErrors('email');

    confirmedAs($admin)->post(route('admin.users.store'), [
        'name' => 'Root', 'email' => 'root@example.com', 'role' => 'superadmin',
    ])->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'root@example.com')->exists())->toBeFalse();
});

test('an administrator updates another account; a new email must be verified again', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->editor()->create(['email' => 'old@example.com']);

    confirmedAs($admin)->put(route('admin.users.update', $user), [
        'updated_at' => $user->updated_at->toIso8601String(),
        'name' => 'Renamed',
        'email' => 'new@example.com',
        'role' => 'admin',
    ])->assertRedirect(route('admin.users.edit', $user));

    $user->refresh();
    expect($user->name)->toBe('Renamed')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->role)->toBe(UserRole::Admin);

    Notification::assertSentTo($user, VerifyEmail::class);
    expect(AuditLog::query()->orderBy('id')->pluck('action')->map->value->all())
        ->toBe([AuditAction::UserUpdated->value, AuditAction::UserRoleChanged->value]);
});

test('administrators cannot change their own role but can edit their name', function () {
    $admin = User::factory()->admin()->create(['name' => 'Me']);

    confirmedAs($admin)->put(route('admin.users.update', $admin), [
        'updated_at' => $admin->updated_at->toIso8601String(),
        'name' => 'Me', 'email' => $admin->email, 'role' => 'editor',
    ])->assertSessionHasErrors('role');
    expect($admin->refresh()->role)->toBe(UserRole::Admin);

    confirmedAs($admin)->put(route('admin.users.update', $admin), [
        'updated_at' => $admin->updated_at->toIso8601String(),
        'name' => 'Still me', 'email' => $admin->email, 'role' => 'admin',
    ])->assertSessionHasNoErrors();
    expect($admin->refresh()->name)->toBe('Still me');

    $this->actingAs($admin)->get(route('admin.users.edit', $admin))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/users/edit', false)
            ->where('can.changeRole', false)
            ->where('can.delete', false)
        );
});

test('a stale form version is rejected as a conflict without changes', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->editor()->create(['name' => 'Original', 'updated_at' => now()]);

    confirmedAs($admin)->put(route('admin.users.update', $user), [
        'updated_at' => now()->subMinute()->toIso8601String(),
        'name' => 'Changed', 'email' => $user->email, 'role' => 'editor',
    ])->assertSessionHasErrors('conflict');

    expect($user->refresh()->name)->toBe('Original');
});

test('an administrator deletes another account; authored content keeps a null author', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->editor()->create();
    $page = Page::factory()->create(['created_by' => $user->id]);

    confirmedAs($admin)->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and($page->refresh()->created_by)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::UserDeleted->value)->sole()->changes)
        ->toBe(['role' => ['old' => 'editor', 'new' => null]]);
});

test('administrators cannot delete their own account from the panel', function () {
    $admin = User::factory()->admin()->create();

    confirmedAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('the last administrator can be neither demoted nor deleted', function () {
    // Locally every account acts as an administrator, so a user without a
    // role can reach the only account that holds the administrator role.
    // CSRF verification is only skipped for the `testing` environment.
    $this->app['env'] = 'local';
    $this->withoutMiddleware(PreventRequestForgery::class);
    $actor = User::factory()->create();
    $onlyAdmin = User::factory()->admin()->create();

    confirmedAs($actor)->put(route('admin.users.update', $onlyAdmin), [
        'updated_at' => $onlyAdmin->updated_at->toIso8601String(),
        'name' => $onlyAdmin->name, 'email' => $onlyAdmin->email, 'role' => 'editor',
    ])->assertSessionHasErrors(['role' => __('admin.users.lastAdministrator')]);

    confirmedAs($actor)->delete(route('admin.users.destroy', $onlyAdmin))
        ->assertSessionHasErrors(['user' => __('admin.users.lastAdministrator')]);

    expect($onlyAdmin->refresh()->role)->toBe(UserRole::Admin)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a created account can set its password through the invitation link', function () {
    $admin = User::factory()->admin()->create();

    confirmedAs($admin)->post(route('admin.users.store'), [
        'name' => 'Invited', 'email' => 'invited@example.com', 'role' => 'editor',
    ]);
    $user = User::query()->where('email', 'invited@example.com')->sole();
    $token = Password::broker()->createToken($user);

    auth()->logout();
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'invited@example.com',
        'password' => 'a-Strong-password-2026!',
        'password_confirmation' => 'a-Strong-password-2026!',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('a-Strong-password-2026!', $user->refresh()->password))->toBeTrue();
});
