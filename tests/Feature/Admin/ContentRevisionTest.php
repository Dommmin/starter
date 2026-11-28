<?php

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Services\Content\ContentSnapshots;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

function revisablePage(): Page
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->locale('en')->published()->create(['title' => 'First', 'slug' => 'first']);

    return $page->refresh();
}

/**
 * @return array<string, mixed>
 */
function pageUpdatePayload(Page $page, string $title, string $slug): array
{
    return [
        'updated_at' => $page->refresh()->updated_at?->toIso8601String(),
        'translations' => ['en' => ['title' => $title, 'slug' => $slug, 'meta_description' => null, 'body' => null, 'status' => 'published']],
    ];
}

test('an update keeps the replaced content and a restore brings back texts but not the address', function () {
    $editor = User::factory()->editor()->create();
    $page = revisablePage();

    $this->actingAs($editor)->put(route('admin.pages.update', $page), pageUpdatePayload($page, 'Second', 'second'))
        ->assertSessionHasNoErrors();

    $revision = ContentRevision::query()->sole();
    expect($revision->snapshot['translations']['en']['title'])->toBe('First')
        ->and($revision->author_id)->toBe($editor->id);

    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, $revision->id]))
        ->assertRedirect(route('admin.pages.edit', $page));

    $translation = $page->translations()->sole();
    expect($translation->title)->toBe('First')
        ->and($translation->slug)->toBe('second')
        ->and($translation->status)->toBe(PublicationStatus::Published)
        ->and(ContentRevision::query()->latest('id')->first()?->snapshot['translations']['en']['title'])->toBe('Second')
        ->and(AuditLog::query()->where('action', 'page.revision_restored')->count())->toBe(1);
});

test('section changes are versioned and restored with the page', function () {
    $editor = User::factory()->editor()->create();
    $page = revisablePage();

    $this->actingAs($editor)->post(route('admin.pages.sections.store', $page), ['locale' => 'en', 'type' => 'hero']);
    $this->actingAs($editor)->delete(route('admin.pages.sections.destroy', [$page, $page->sections()->sole()]));

    expect($page->sections()->count())->toBe(0);

    $withSection = ContentRevision::query()->latest('id')->firstOrFail();
    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, $withSection->id]));

    expect($page->sections()->sole()->type->value)->toBe('hero');
});

test('only the newest revisions of a record are kept', function () {
    $page = revisablePage();
    $snapshots = app(ContentSnapshots::class);

    foreach (range(1, ContentSnapshots::KEEP + 3) as $number) {
        $page->translations()->update(['title' => "Version {$number}"]);
        $snapshots->record($page, null);
    }

    expect($snapshots->query($page)->count())->toBe(ContentSnapshots::KEEP)
        ->and($snapshots->query($page)->orderBy('id')->first()?->snapshot['translations']['en']['title'])->toBe('Version 4');
});

test('a viewer cannot restore and does not receive the history', function () {
    $viewer = User::factory()->viewer()->create();
    $page = revisablePage();
    app(ContentSnapshots::class)->record($page, null);
    $page->translations()->update(['title' => 'Current']);

    $this->actingAs($viewer)->post(route('admin.pages.revisions.restore', [$page, ContentRevision::query()->sole()->id]))
        ->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.pages.edit', $page))
        ->assertInertia(fn (Assert $assert) => $assert->missing('revisions'));

    expect($page->translations()->sole()->title)->toBe('Current');
});

test('a revision of another record cannot be restored here', function () {
    $editor = User::factory()->editor()->create();
    $page = revisablePage();
    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->locale('en')->create(['title' => 'Article title']);
    app(ContentSnapshots::class)->record($article, null);

    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, ContentRevision::query()->sole()->id]))
        ->assertNotFound();

    expect($page->translations()->sole()->title)->toBe('First');
});
