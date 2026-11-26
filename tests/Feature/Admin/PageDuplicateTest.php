<?php

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * A page translated into every public locale: English and Polish published,
 * German a draft, the English slug with a former slug redirect.
 */
function pageInEveryLocale(): Page
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->locale('en')->published()->create(['title' => 'About us', 'slug' => 'about-us']);
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['title' => 'O nas', 'slug' => 'o-nas']);
    PageTranslation::factory()->for($page)->locale('de')->draft()->create(['title' => 'Über uns', 'slug' => 'uber-uns']);

    $english = $page->translations()->where('locale', 'en')->sole();
    PageSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'about', 'page_translation_id' => $english->id]);

    return $page;
}

/**
 * @return array<string, array<string, mixed>>
 */
function pageTranslationSnapshot(Page $page): array
{
    return PageTranslation::query()->whereBelongsTo($page)->orderBy('locale')->get()
        ->mapWithKeys(fn (PageTranslation $translation): array => [$translation->locale => $translation->getAttributes()])
        ->all();
}

test('admins and editors copy a page with every translation as an unpublished draft', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $source = pageInEveryLocale();
    $sourceBefore = $source->fresh()?->getAttributes();
    $translationsBefore = pageTranslationSnapshot($source);

    $response = $this->actingAs($user)->post(route('admin.pages.duplicate', $source));

    $copy = Page::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('admin.pages.edit', $copy))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.pages.duplicated')]);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->created_by)->toBe($user->id)
        ->and($copy->updated_by)->toBe($user->id);

    $translations = $copy->translations()->get()->keyBy('locale');
    expect($translations->keys()->sort()->values()->all())->toBe(['de', 'en', 'pl']);

    foreach (['en' => ['About us', 'about-us-copy-1'], 'pl' => ['O nas', 'o-nas-copy-1'], 'de' => ['Über uns', 'uber-uns-copy-1']] as $locale => [$title, $slug]) {
        $translation = $translations[$locale];
        $original = $source->translations()->where('locale', $locale)->sole();

        expect($translation->title)->toBe($title)
            ->and($translation->slug)->toBe($slug)
            ->and($translation->status)->toBe(PublicationStatus::Draft)
            ->and($translation->published_at)->toBeNull()
            ->and($translation->meta_description)->toBe($original->meta_description)
            ->and($translation->body)->toEqual($original->body);
    }

    expect(PageSlugRedirect::query()->whereIn('page_translation_id', $translations->pluck('id'))->exists())->toBeFalse()
        ->and(PageSlugRedirect::query()->count())->toBe(1)
        ->and($source->fresh()?->getAttributes())->toBe($sourceBefore)
        ->and(pageTranslationSnapshot($source))->toBe($translationsBefore);

    $entry = AuditLog::query()->sole();

    expect($entry->action)->toBe(AuditAction::PageDuplicated)
        ->and($entry->actor_id)->toBe($user->id)
        ->and($entry->subject_type)->toBe($copy->getMorphClass())
        ->and($entry->subject_id)->toBe($copy->id)
        ->and($entry->changes)->toEqual([
            'duplicated_from' => ['old' => null, 'new' => $source->id],
            'en.slug' => ['old' => null, 'new' => 'about-us-copy-1'],
            'pl.slug' => ['old' => null, 'new' => 'o-nas-copy-1'],
            'de.slug' => ['old' => null, 'new' => 'uber-uns-copy-1'],
        ]);
})->with(['admin', 'editor']);

test('each copy takes the first free copy slug in every locale', function () {
    $editor = User::factory()->editor()->create();
    $source = Page::factory()->create();
    foreach (['en', 'pl', 'de'] as $locale) {
        PageTranslation::factory()->for($source)->locale($locale)->published()->create(['slug' => 'about']);
    }

    // English: taken by a page in the trash and by a redirect; Polish: by a live page.
    $trashed = Page::factory()->create();
    PageTranslation::factory()->for($trashed)->locale('en')->create(['slug' => 'about-copy-1']);
    $trashed->delete();
    $other = Page::factory()->create();
    $otherEnglish = PageTranslation::factory()->for($other)->locale('en')->create(['slug' => 'other']);
    PageSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'about-copy-2', 'page_translation_id' => $otherEnglish->id]);
    PageTranslation::factory()->for($other)->locale('pl')->create(['slug' => 'about-copy-1']);

    $this->actingAs($editor)->post(route('admin.pages.duplicate', $source))->assertRedirect();
    $first = Page::query()->latest('id')->firstOrFail();

    $this->actingAs($editor)->post(route('admin.pages.duplicate', $source))->assertRedirect();
    $second = Page::query()->latest('id')->firstOrFail();

    $this->actingAs($editor)->post(route('admin.pages.duplicate', $first))->assertRedirect();
    $copyOfCopy = Page::query()->latest('id')->firstOrFail();

    $slugs = fn (Page $page): array => $page->translations()->pluck('slug', 'locale')->sortKeys()->all();

    expect($slugs($first))->toBe(['de' => 'about-copy-1', 'en' => 'about-copy-3', 'pl' => 'about-copy-2'])
        ->and($slugs($second))->toBe(['de' => 'about-copy-2', 'en' => 'about-copy-4', 'pl' => 'about-copy-3'])
        ->and($slugs($copyOfCopy))->toBe(['de' => 'about-copy-3', 'en' => 'about-copy-5', 'pl' => 'about-copy-4'])
        ->and(PageSlugRedirect::query()->where('old_slug', 'about-copy-2')->sole()->page_translation_id)->toBe($otherEnglish->id);
});

test('a copy is not visible to visitors until it is published', function () {
    $source = pageInEveryLocale();

    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.pages.duplicate', $source))
        ->assertRedirect();

    auth()->logout();

    $this->get('/about-us')->assertOk();
    $this->get('/about-us-copy-1')->assertNotFound();
    $this->get('/pl/o-nas')->assertOk();
    $this->get('/pl/o-nas-copy-1')->assertNotFound();
});

test('a viewer cannot copy a page and nothing is stored', function () {
    $source = pageInEveryLocale();

    $this->actingAs(User::factory()->viewer()->create())
        ->post(route('admin.pages.duplicate', $source))
        ->assertForbidden();

    expect(Page::query()->count())->toBe(1)
        ->and(PageTranslation::query()->count())->toBe(3)
        ->and(PageSlugRedirect::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the page editor offers copying only to roles that may copy', function (string $role, bool $canDuplicate) {
    $page = Page::factory()->draft()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/edit', false)
            ->where('can.duplicate', $canDuplicate)
        );
})->with([
    'editor' => ['editor', true],
    'viewer' => ['viewer', false],
]);
