<?php

use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('a deleted article goes to the trash and disappears from the website and the panel lists', function () {
    $admin = User::factory()->admin()->create();
    $translation = ArticleTranslation::factory()->published(now()->subDay())->create(['slug' => 'hello', 'title' => 'Hello']);
    $article = $translation->article;

    $this->actingAs($admin)->delete(route('admin.articles.destroy', $article))->assertRedirect(route('admin.articles.index'));

    expect($article->fresh()?->trashed())->toBeTrue()
        ->and(ArticleTranslation::query()->count())->toBe(0)
        ->and(ArticleTranslation::query()->withoutGlobalScope(ArticleTranslation::LIVE_PARENT_SCOPE)->count())->toBe(1);

    auth()->logout();
    $this->get('/articles/hello')->assertNotFound();
    $this->get('/articles')->assertInertia(fn (Assert $page) => $page->has('items', 0));
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('hello');

    $this->actingAs($admin)->get(route('admin.articles.index'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 0));
    $this->actingAs($admin)->get(route('admin.articles.edit', $article))->assertNotFound();
    $this->actingAs($admin)->getJson(route('admin.search', ['q' => 'hello']))->assertJsonCount(0, 'results');
});

test('the slug of a trashed article stays taken', function () {
    $editor = User::factory()->editor()->create();
    $translation = ArticleTranslation::factory()->create(['locale' => 'en', 'slug' => 'taken']);
    $translation->article->delete();

    $this->actingAs($editor)->post(route('admin.articles.store'), [
        'translations' => ['en' => ['title' => 'Again', 'slug' => 'taken', 'status' => 'draft']],
    ])->assertSessionHasErrors('translations.en.slug');
});

test('an administrator sees the trash, restores a page and it is visible again', function () {
    $admin = User::factory()->admin()->create();
    $translation = PageTranslation::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
    $page = $translation->page;
    $this->actingAs($admin)->delete(route('admin.pages.destroy', $page));

    $this->actingAs($admin)->get(route('admin.trash.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/trash/index', false)
            ->where('retentionDays', 30)
            ->where('items.0.type', 'pages')
            ->where('items.0.id', $page->id)
            ->where('items.0.title', 'About us')
            ->where('items.0.locales', ['en'])
        );

    $this->actingAs($admin)->put(route('admin.trash.restore', ['type' => 'pages', 'id' => $page->id]))
        ->assertRedirect(route('admin.pages.edit', $page));

    expect($page->fresh()?->trashed())->toBeFalse()
        ->and(AuditLog::query()->latest('id')->first()?->action)->toBe(AuditAction::PageRestored);

    auth()->logout();
    $this->get('/about')->assertOk();
});

test('emptying the trash removes the record with its translations for good', function () {
    $admin = User::factory()->admin()->create();
    $article = ArticleTranslation::factory()->create(['title' => 'Old news'])->article;
    $article->delete();

    $this->actingAs($admin)->delete(route('admin.trash.destroy', ['type' => 'articles', 'id' => $article->id]))
        ->assertRedirect(route('admin.trash.index'));

    $audit = AuditLog::query()->latest('id')->firstOrFail();
    expect(Article::withTrashed()->count())->toBe(0)
        ->and(ArticleTranslation::query()->withoutGlobalScope(ArticleTranslation::LIVE_PARENT_SCOPE)->count())->toBe(0)
        ->and($audit->action)->toBe(AuditAction::ArticlePurged)
        ->and($audit->changes['en.title'] ?? null)->toBe(['old' => 'Old news', 'new' => null]);
});

test('only records in the trash can be restored or removed for good', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->create();

    $this->actingAs($admin)->put(route('admin.trash.restore', ['type' => 'pages', 'id' => $page->id]))->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.trash.destroy', ['type' => 'pages', 'id' => $page->id]))->assertNotFound();

    expect(Page::query()->count())->toBe(1);
});

test('editors and viewers cannot reach the trash and change nothing', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $page = Page::factory()->create();
    $page->delete();

    $this->actingAs($user)->get(route('admin.trash.index'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.trash.restore', ['type' => 'pages', 'id' => $page->id]))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.trash.destroy', ['type' => 'pages', 'id' => $page->id]))->assertForbidden();

    expect(Page::onlyTrashed()->count())->toBe(1);
})->with(['editor', 'viewer']);

test('the daily cleanup removes only records older than the retention period', function () {
    $old = Page::factory()->create();
    $recent = Page::factory()->create();
    $this->travel(-31)->days();
    $old->delete();
    $this->travelBack();
    $recent->delete();

    $this->artisan('content:purge-trash')->expectsOutputToContain('Removed 1 trashed records')->assertSuccessful();

    expect(Page::withTrashed()->pluck('id')->all())->toBe([$recent->id])
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::PagePurged)
        ->and(AuditLog::query()->sole()->actor_id)->toBeNull();
});
