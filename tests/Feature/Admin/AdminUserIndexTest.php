<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

test('users without confirmed two factor authentication are redirected to security settings', function () {
    config(['fortify.require_two_factor_for_admin' => true]);

    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get(route('admin.users.index'));

    $response->assertRedirect(route('security.edit'));
});

test('admins can list users with default filters', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('items', 4)
            ->where('filters.search', '')
            ->where('filters.verified', 'all')
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
            ->where('pagination.total', 4)
        );
});

test('search filters users by name or email', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'ada']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->where('filters.search', 'ada')
        ->has('items', 1)
        ->where('items.0.email', 'ada@example.com')
    );
});

test('verified filter narrows the results to matching accounts', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    User::factory()->unverified()->create();
    User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['verified' => 'unverified']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->where('filters.verified', 'unverified')
        ->has('items', 1)
        ->where('items.0.verified', false)
    );
});

test('sorting by name ascending orders the returned users', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['name' => 'Zeta Admin']);
    User::factory()->create(['name' => 'Bob']);
    User::factory()->create(['name' => 'Alice']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', [
        'sort' => 'name',
        'direction' => 'asc',
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->where('filters.sort', 'name')
        ->where('filters.direction', 'asc')
        ->where('items.0.name', 'Alice')
        ->where('items.1.name', 'Bob')
        ->where('items.2.name', 'Zeta Admin')
    );
});

test('the requested query state round-trips through the response filters', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index', [
        'search' => 'lovelace',
        'verified' => 'verified',
        'sort' => 'email',
        'direction' => 'asc',
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->where('filters.search', 'lovelace')
        ->where('filters.verified', 'verified')
        ->where('filters.sort', 'email')
        ->where('filters.direction', 'asc')
    );
});

test('invalid sort and verified values are rejected', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index', [
        'sort' => 'password',
        'verified' => 'anything',
    ]));

    $response->assertSessionHasErrors(['sort', 'verified']);
});

test('an out-of-allowlist direction or page is rejected without querying', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->get(route('admin.users.index', ['direction' => 'desc; drop table users', 'page' => 'x']));

    $response->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['direction', 'page']);
});

test('search treats like wildcards literally', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['name' => 'Admin']);
    User::factory()->create(['name' => 'Ada 100% Lovelace']);
    User::factory()->create(['name' => 'Ada 1000 Lovelace']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => '100%']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('items', 1)
        ->where('items.0.name', 'Ada 100% Lovelace')
    );
});

test('pagination metadata reflects the requested page', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    User::factory()->count(11)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['page' => 2]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('items', 2)
        ->where('pagination.page', 2)
        ->where('pagination.totalPages', 2)
        ->where('pagination.total', 12)
        ->where('pagination.perPage', 10)
    );
});
