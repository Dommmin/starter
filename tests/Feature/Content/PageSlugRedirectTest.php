<?php

use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use App\Models\User;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    $this->admin = User::factory()->admin()->create();
});

/**
 * Submit the page editor with one translation per given locale.
 *
 * @param  array<string, array{0: string, 1: string}>  $translations  locale => [slug, status]
 */
function savePageSlugs(Page $page, array $translations): void
{
    $page->refresh();
    test()->travel(1)->seconds();

    $input = [];
    foreach ($translations as $locale => [$slug, $status]) {
        $input[$locale] = ['title' => "Title {$slug}", 'slug' => $slug, 'status' => $status];
    }

    test()->actingAs(test()->admin)->put(route('admin.pages.update', $page), [
        'updated_at' => $page->updated_at->toIso8601String(),
        'translations' => $input,
    ])->assertSessionHasNoErrors()->assertRedirect();
}

test('a changed slug of a published page answers with a single 301 to the current url', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'old-slug']);

    savePageSlugs($page, ['en' => ['new-slug', 'published']]);

    $this->get('/old-slug')->assertStatus(301)->assertRedirect(url('/new-slug'));
    $this->get('/new-slug')->assertOk();
});

test('two slug changes do not create a redirect chain', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'first']);

    savePageSlugs($page, ['en' => ['second', 'published']]);
    savePageSlugs($page, ['en' => ['third', 'published']]);

    $this->get('/first')->assertStatus(301)->assertRedirect(url('/third'));
    $this->get('/second')->assertStatus(301)->assertRedirect(url('/third'));
});

test('returning to a former slug removes its redirect and avoids a loop', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'original']);

    savePageSlugs($page, ['en' => ['renamed', 'published']]);
    savePageSlugs($page, ['en' => ['original', 'published']]);

    expect(PageSlugRedirect::query()->where('old_slug', 'original')->exists())->toBeFalse()
        ->and(PageSlugRedirect::query()->where('old_slug', 'renamed')->exists())->toBeTrue();

    $this->get('/original')->assertOk();
    $this->get('/renamed')->assertStatus(301)->assertRedirect(url('/original'));
});

test('a never published draft keeps no redirect and a redirect to a draft is a 404', function () {
    $draftPage = Page::factory()->create();
    PageTranslation::factory()->for($draftPage)->draft()->create(['slug' => 'draft-old']);

    savePageSlugs($draftPage, ['en' => ['draft-new', 'draft']]);

    expect(PageSlugRedirect::query()->count())->toBe(0);
    $this->get('/draft-old')->assertNotFound();

    $unpublished = Page::factory()->create();
    PageTranslation::factory()->for($unpublished)->published()->create(['slug' => 'was-public']);

    savePageSlugs($unpublished, ['en' => ['now-hidden', 'draft']]);

    expect(PageSlugRedirect::query()->where('old_slug', 'was-public')->exists())->toBeTrue();
    $this->get('/was-public')->assertNotFound();
});

test('redirects are isolated per locale', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'company']);
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['slug' => 'o-nas']);

    savePageSlugs($page, [
        'en' => ['company', 'published'],
        'pl' => ['o-firmie', 'published'],
    ]);

    $this->get('/pl/o-nas')->assertStatus(301)->assertRedirect(url('/pl/o-firmie'));
    $this->get('/o-nas')->assertNotFound();
    $this->get('/de/o-nas')->assertNotFound();
});

test('a redirect never shadows a published page and yields its slug to another page', function () {
    $moved = Page::factory()->create();
    PageTranslation::factory()->for($moved)->published()->create(['slug' => 'contact']);
    savePageSlugs($moved, ['en' => ['contact-us', 'published']]);

    $other = Page::factory()->create();
    PageTranslation::factory()->for($other)->published()->create(['slug' => 'placeholder']);
    savePageSlugs($other, ['en' => ['contact', 'published']]);

    expect(PageSlugRedirect::query()->where('old_slug', 'contact')->exists())->toBeFalse();

    $this->get('/contact')->assertOk()->assertSee('Title contact');
});

test('deleting a page removes its redirects', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'gone']);
    savePageSlugs($page, ['en' => ['gone-too', 'published']]);

    $this->actingAs($this->admin)->delete(route('admin.pages.destroy', $page))->assertRedirect();

    expect(PageSlugRedirect::query()->count())->toBe(0);
    $this->get('/gone')->assertNotFound();
});
