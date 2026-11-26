<?php

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * An article with a cover and two tags, translated into every public locale:
 * English published, Polish scheduled, German a draft, the English slug with
 * a former slug redirect.
 */
function articleInEveryLocale(): Article
{
    $article = Article::factory()->create(['cover_media_id' => MediaAsset::factory()->clean()->create()->id]);
    $article->tags()->sync(Tag::factory()->count(2)->create()->modelKeys());

    ArticleTranslation::factory()->for($article)->locale('en')->published()->create(['title' => 'Launch notes', 'slug' => 'launch-notes']);
    ArticleTranslation::factory()->for($article)->locale('pl')->scheduled()->create(['title' => 'Notatki', 'slug' => 'notatki']);
    ArticleTranslation::factory()->for($article)->locale('de')->draft()->create(['title' => 'Notizen', 'slug' => 'notizen']);

    $english = $article->translations()->where('locale', 'en')->sole();
    ArticleSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'launch', 'article_translation_id' => $english->id]);

    return $article;
}

/**
 * @return array<string, array<string, mixed>>
 */
function articleTranslationSnapshot(Article $article): array
{
    return ArticleTranslation::query()->whereBelongsTo($article)->orderBy('locale')->get()
        ->mapWithKeys(fn (ArticleTranslation $translation): array => [$translation->locale => $translation->getAttributes()])
        ->all();
}

test('admins and editors copy an article with cover, tags and translations as an unpublished draft', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $source = articleInEveryLocale();
    $sourceBefore = $source->fresh()?->getAttributes();
    $tagsBefore = $source->tags()->pluck('tags.id')->sort()->values()->all();
    $translationsBefore = articleTranslationSnapshot($source);

    $response = $this->actingAs($user)->post(route('admin.articles.duplicate', $source));

    $copy = Article::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('admin.articles.edit', $copy))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.articles.duplicated')]);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->cover_media_id)->toBe($source->cover_media_id)
        ->and($copy->tags()->pluck('tags.id')->sort()->values()->all())->toBe($tagsBefore)
        ->and($copy->created_by)->toBe($user->id)
        ->and($copy->updated_by)->toBe($user->id);

    $translations = $copy->translations()->get()->keyBy('locale');
    expect($translations->keys()->sort()->values()->all())->toBe(['de', 'en', 'pl']);

    foreach (['en' => ['Launch notes', 'launch-notes-copy-1'], 'pl' => ['Notatki', 'notatki-copy-1'], 'de' => ['Notizen', 'notizen-copy-1']] as $locale => [$title, $slug]) {
        $translation = $translations[$locale];
        $original = $source->translations()->where('locale', $locale)->sole();

        expect($translation->title)->toBe($title)
            ->and($translation->slug)->toBe($slug)
            ->and($translation->status)->toBe(PublicationStatus::Draft)
            ->and($translation->published_at)->toBeNull()
            ->and($translation->excerpt)->toBe($original->excerpt)
            ->and($translation->meta_description)->toBe($original->meta_description)
            ->and($translation->body)->toEqual($original->body);
    }

    expect(ArticleSlugRedirect::query()->whereIn('article_translation_id', $translations->pluck('id'))->exists())->toBeFalse()
        ->and(ArticleSlugRedirect::query()->count())->toBe(1)
        ->and($source->fresh()?->getAttributes())->toBe($sourceBefore)
        ->and($source->tags()->pluck('tags.id')->sort()->values()->all())->toBe($tagsBefore)
        ->and(articleTranslationSnapshot($source))->toBe($translationsBefore);

    $entry = AuditLog::query()->sole();

    expect($entry->action)->toBe(AuditAction::ArticleDuplicated)
        ->and($entry->actor_id)->toBe($user->id)
        ->and($entry->subject_type)->toBe($copy->getMorphClass())
        ->and($entry->subject_id)->toBe($copy->id)
        ->and($entry->changes)->toEqual([
            'duplicated_from' => ['old' => null, 'new' => $source->id],
            'en.slug' => ['old' => null, 'new' => 'launch-notes-copy-1'],
            'pl.slug' => ['old' => null, 'new' => 'notatki-copy-1'],
            'de.slug' => ['old' => null, 'new' => 'notizen-copy-1'],
        ]);
})->with(['admin', 'editor']);

test('each article copy takes the first free copy slug in every locale', function () {
    $editor = User::factory()->editor()->create();
    $source = Article::factory()->create();
    foreach (['en', 'pl', 'de'] as $locale) {
        ArticleTranslation::factory()->for($source)->locale($locale)->published()->create(['slug' => 'news']);
    }

    // English: taken by an article in the trash and by a redirect; Polish: by a live article.
    $trashed = Article::factory()->create();
    ArticleTranslation::factory()->for($trashed)->locale('en')->create(['slug' => 'news-copy-1']);
    $trashed->delete();
    $other = Article::factory()->create();
    $otherEnglish = ArticleTranslation::factory()->for($other)->locale('en')->create(['slug' => 'other']);
    ArticleSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'news-copy-2', 'article_translation_id' => $otherEnglish->id]);
    ArticleTranslation::factory()->for($other)->locale('pl')->create(['slug' => 'news-copy-1']);

    $this->actingAs($editor)->post(route('admin.articles.duplicate', $source))->assertRedirect();
    $first = Article::query()->latest('id')->firstOrFail();

    $this->actingAs($editor)->post(route('admin.articles.duplicate', $source))->assertRedirect();
    $second = Article::query()->latest('id')->firstOrFail();

    $this->actingAs($editor)->post(route('admin.articles.duplicate', $first))->assertRedirect();
    $copyOfCopy = Article::query()->latest('id')->firstOrFail();

    $slugs = fn (Article $article): array => $article->translations()->pluck('slug', 'locale')->sortKeys()->all();

    expect($slugs($first))->toBe(['de' => 'news-copy-1', 'en' => 'news-copy-3', 'pl' => 'news-copy-2'])
        ->and($slugs($second))->toBe(['de' => 'news-copy-2', 'en' => 'news-copy-4', 'pl' => 'news-copy-3'])
        ->and($slugs($copyOfCopy))->toBe(['de' => 'news-copy-3', 'en' => 'news-copy-5', 'pl' => 'news-copy-4'])
        ->and(ArticleSlugRedirect::query()->where('old_slug', 'news-copy-2')->sole()->article_translation_id)->toBe($otherEnglish->id);
});

test('an article copy is not visible to visitors until it is published', function () {
    $source = articleInEveryLocale();

    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.articles.duplicate', $source))
        ->assertRedirect();

    auth()->logout();

    $this->get('/articles/launch-notes')->assertOk();
    $this->get('/articles/launch-notes-copy-1')->assertNotFound();
    $this->get('/articles')->assertOk()->assertDontSee('launch-notes-copy-1');
});

test('a viewer cannot copy an article and nothing is stored', function () {
    $source = articleInEveryLocale();

    $this->actingAs(User::factory()->viewer()->create())
        ->post(route('admin.articles.duplicate', $source))
        ->assertForbidden();

    expect(Article::query()->count())->toBe(1)
        ->and(ArticleTranslation::query()->count())->toBe(3)
        ->and(ArticleSlugRedirect::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the article editor offers copying only to roles that may copy', function (string $role, bool $canDuplicate) {
    $article = Article::factory()->draft()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.articles.edit', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/articles/edit', false)
            ->where('can.duplicate', $canDuplicate)
        );
})->with([
    'editor' => ['editor', true],
    'viewer' => ['viewer', false],
]);
