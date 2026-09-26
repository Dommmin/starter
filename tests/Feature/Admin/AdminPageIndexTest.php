<?php

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

function pageWithTitle(string $title, PublicationStatus $status = PublicationStatus::Draft, string $locale = 'en'): Page
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->locale($locale)->create([
        'title' => $title,
        'slug' => Str::slug($title),
        'status' => $status,
    ]);

    return $page;
}

test('the page list exposes the typed list payload with defaults', function () {
    $editor = User::factory()->editor()->create();
    $page = pageWithTitle('About us', PublicationStatus::Published);
    PageTranslation::factory()->for($page)->locale('pl')->create(['slug' => 'o-nas']);

    $this->actingAs($editor)->get(route('admin.pages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->has('items', 1)
            ->where('items.0.id', $page->id)
            ->where('items.0.title', 'About us')
            ->where('items.0.slug', 'about-us')
            ->where('items.0.status', 'published')
            ->where('items.0.locale', 'en')
            ->where('items.0.locales', ['en', 'pl'])
            ->where('items.0.updatedAt', $page->fresh()->updated_at->toIso8601String())
            ->where('pagination', ['page' => 1, 'totalPages' => 1, 'total' => 1, 'perPage' => 15])
            ->where('filters', [
                'search' => '',
                'sort' => 'updated_at',
                'direction' => 'desc',
                'locale' => 'en',
                'status' => 'all',
            ])
            ->where('locales.default', 'en')
            ->where('locales.available.1.code', 'pl')
            ->where('can', ['create' => true, 'publish' => true, 'delete' => false])
        );
});

test('search matches the title or slug of the selected content locale', function () {
    $admin = User::factory()->admin()->create();
    pageWithTitle('Privacy policy');
    pageWithTitle('Terms of service');
    pageWithTitle('Privatsphäre', locale: 'de');

    $this->actingAs($admin)->get(route('admin.pages.index', ['search' => 'privacy']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->has('items', 1)
            ->where('items.0.title', 'Privacy policy')
        );

    $this->actingAs($admin)->get(route('admin.pages.index', ['search' => 'terms-of']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->has('items', 1)
            ->where('items.0.slug', 'terms-of-service')
        );

    $this->actingAs($admin)->get(route('admin.pages.index', ['search' => 'priva', 'locale' => 'de']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->has('items', 1)
            ->where('items.0.locale', 'de')
            ->where('filters.locale', 'de')
        );
});

test('the status filter narrows the list', function () {
    $admin = User::factory()->admin()->create();
    pageWithTitle('Draft page');
    pageWithTitle('Live page', PublicationStatus::Published);

    $this->actingAs($admin)->get(route('admin.pages.index', ['status' => 'published']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->has('items', 1)
            ->where('items.0.title', 'Live page')
            ->where('filters.status', 'published')
        );
});

test('sorting by title uses the translation of the content locale', function () {
    $admin = User::factory()->admin()->create();
    pageWithTitle('Bravo');
    pageWithTitle('Alpha');
    pageWithTitle('Charlie');

    $this->actingAs($admin)->get(route('admin.pages.index', ['sort' => 'title', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->where('items.0.title', 'Alpha')
            ->where('items.1.title', 'Bravo')
            ->where('items.2.title', 'Charlie')
        );
});

test('sort, status and locale values outside the allowlists are rejected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.pages.index'))
        ->get(route('admin.pages.index', ['sort' => 'slug', 'status' => 'archived', 'locale' => 'fr']))
        ->assertRedirect(route('admin.pages.index'))
        ->assertSessionHasErrors(['sort', 'status', 'locale']);
});

test('pagination splits the list into pages of fifteen', function () {
    $admin = User::factory()->admin()->create();
    Page::factory()->count(16)->draft()->create();

    $this->actingAs($admin)->get(route('admin.pages.index', ['page' => 2]))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/index', false)
            ->has('items', 1)
            ->where('pagination.page', 2)
            ->where('pagination.totalPages', 2)
            ->where('pagination.total', 16)
        );
});

test('users without a panel role cannot open any page screen', function (string $method, string $routeName, bool $withPage) {
    $user = User::factory()->create();
    $page = Page::factory()->draft()->create();
    $url = route($routeName, $withPage ? $page : []);

    $this->actingAs($user)->{$method}($url)->assertForbidden();
})->with([
    ['get', 'admin.pages.index', false],
    ['get', 'admin.pages.create', false],
    ['get', 'admin.pages.edit', true],
]);

test('guests are redirected to login from the page list', function () {
    $this->get(route('admin.pages.index'))->assertRedirect(route('login'));
});
