<?php

use App\Models\SavedListView;
use App\Models\User;
use App\Support\Listing\SavedListViews;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('a user saves the current list parameters under a name, without the page number', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->postJson(route('admin.saved-views.store'), [
        'list' => 'admin.articles.index',
        'name' => 'My drafts',
        'query' => ['status' => 'draft', 'page' => '3', 'search' => '', 'sort' => 'title', 'Weird-Key' => 'x'],
    ])
        ->assertOk()
        ->assertJsonPath('views.0.name', 'My drafts')
        ->assertJsonPath('views.0.url', route('admin.articles.index', ['sort' => 'title', 'status' => 'draft']))
        ->assertJsonPath('max', SavedListViews::MAX_PER_LIST);

    $view = SavedListView::query()->sole();
    expect($view->user_id)->toBe($editor->id)
        ->and($view->query)->toBe(['sort' => 'title', 'status' => 'draft']);
});

test('the same name replaces the view and each user sees only their own views', function () {
    $editor = User::factory()->editor()->create();
    $other = User::factory()->editor()->create();
    SavedListView::factory()->for($other)->create(['name' => 'Theirs']);

    foreach (['draft', 'published'] as $status) {
        $this->actingAs($editor)->postJson(route('admin.saved-views.store'), [
            'list' => 'admin.articles.index', 'name' => 'Status', 'query' => ['status' => $status],
        ])->assertOk();
    }

    $this->actingAs($editor)->getJson(route('admin.saved-views.index', ['list' => 'admin.articles.index']))
        ->assertOk()
        ->assertJsonCount(1, 'views')
        ->assertJsonPath('views.0.url', route('admin.articles.index', ['status' => 'published']));

    expect(SavedListView::query()->count())->toBe(2);
});

test('only admin list routes can be saved', function (string $list) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson(route('admin.saved-views.store'), [
        'list' => $list, 'name' => 'View', 'query' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('list');

    $this->actingAs($admin)->getJson(route('admin.saved-views.index', ['list' => $list]))->assertNotFound();
})->with(['admin.users.edit', 'admin.unknown.index', 'home', 'articles.index']);

test('a user keeps at most the maximum number of views per list', function () {
    $editor = User::factory()->editor()->create();
    SavedListView::factory()->for($editor)->count(SavedListViews::MAX_PER_LIST)->create();

    $this->actingAs($editor)->postJson(route('admin.saved-views.store'), [
        'list' => 'admin.articles.index', 'name' => 'One more', 'query' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->actingAs($editor)->postJson(route('admin.saved-views.store'), [
        'list' => 'admin.pages.index', 'name' => 'Other list', 'query' => [],
    ])->assertOk();
});

test('nobody else removes a view, administrators included', function () {
    $owner = User::factory()->editor()->create();
    $admin = User::factory()->admin()->create();
    $view = SavedListView::factory()->for($owner)->create();

    $this->actingAs($admin)->deleteJson(route('admin.saved-views.destroy', $view))->assertForbidden();
    expect(SavedListView::query()->count())->toBe(1);

    $this->actingAs($owner)->deleteJson(route('admin.saved-views.destroy', $view))
        ->assertOk()
        ->assertJsonCount(0, 'views');
    expect(SavedListView::query()->count())->toBe(0);
});

test('guests and users without a panel role cannot use saved views', function () {
    $this->getJson(route('admin.saved-views.index', ['list' => 'admin.articles.index']))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.saved-views.store'), ['list' => 'admin.articles.index', 'name' => 'X', 'query' => []])
        ->assertForbidden();

    expect(SavedListView::query()->count())->toBe(0);
});
