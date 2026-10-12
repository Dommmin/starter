<?php

use App\Actions\Fortify\CreateNewUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('guests are redirected to the login page from every panel route', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['admin.index', 'admin.users.index']);

test('users without a role cannot access the administration panel', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with(['admin.index', 'admin.users.index']);

test('editors can open the panel but not the user list', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'editor')
            ->where('auth.can.manageUsers', false)
        );

    $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
});

test('admins can open the panel and the user list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'admin')
            ->where('auth.can.manageUsers', true)
        );

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

test('the local environment grants panel access to users without a role', function () {
    $this->app['env'] = 'local';
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', null)
            ->where('auth.can.manageUsers', true)
        );

    $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
});

test('the role attribute is not mass assignable', function () {
    expect(fn () => (new User)->fill(['role' => UserRole::Admin->value]))
        ->toThrow(MassAssignmentException::class);
});

test('updating the profile with a role field does not grant a role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'role' => UserRole::Admin->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->role)->toBeNull();
});

test('creating a user through registration input ignores the role field', function () {
    $user = app(CreateNewUser::class)->create([
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'Secure-Registration-Password-2026!',
        'password_confirmation' => 'Secure-Registration-Password-2026!',
        'role' => UserRole::Admin->value,
    ]);

    expect($user->refresh()->role)->toBeNull();
});

test('the panel access flag follows the role', function (?string $state, bool $expected) {
    $user = $state === null ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.accessAdminPanel', $expected));
})->with([
    'no role' => [null, false],
    'editor' => ['editor', true],
    'admin' => ['admin', true],
]);
