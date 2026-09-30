<?php

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function articleTranslationInput(string $title, string $slug, string $status = 'draft', array $overrides = []): array
{
    return [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => 'Short excerpt.',
        'meta_description' => null,
        'body' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]],
        ],
        'status' => $status,
        'published_on' => null,
        ...$overrides,
    ];
}

test('panel users without a panel role cannot reach any article route and change nothing', function () {
    $user = User::factory()->create();
    $article = Article::factory()->published()->create();

    $this->actingAs($user)->get(route('admin.articles.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.articles.create'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.articles.store'), [
        'translations' => ['en' => articleTranslationInput('Hacked', 'hacked', 'published')],
    ])->assertForbidden();
    $this->actingAs($user)->get(route('admin.articles.edit', $article))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.articles.destroy', $article))->assertForbidden();

    expect(Article::query()->count())->toBe(1)
        ->and(ArticleTranslation::query()->where('slug', 'hacked')->exists())->toBeFalse()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('guests are redirected to the login page', function () {
    $this->get(route('admin.articles.index'))->assertRedirect(route('login'));
});

test('the list describes rows in the content locale and flags scheduled articles', function () {
    $editor = User::factory()->editor()->create();
    $scheduled = Article::factory()->create();
    ArticleTranslation::factory()->for($scheduled)->scheduled()->create(['title' => 'Coming soon']);
    $draft = Article::factory()->create();
    ArticleTranslation::factory()->for($draft)->draft()->create(['title' => 'Work in progress']);

    $this->actingAs($editor)->get(route('admin.articles.index', ['sort' => 'title', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/articles/index', false)
            ->has('items', 2)
            ->where('items.0.title', 'Coming soon')
            ->where('items.0.status', 'published')
            ->where('items.0.scheduled', true)
            ->where('items.1.title', 'Work in progress')
            ->where('items.1.scheduled', false)
            ->where('filters.locale', 'en')
            ->where('can.create', true)
            ->where('can.delete', false)
        );

    $this->actingAs($editor)->get(route('admin.articles.index', ['status' => 'draft']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.title', 'Work in progress')
        );
});

test('the status filter separates visible, scheduled and draft articles', function () {
    $editor = User::factory()->editor()->create();
    ArticleTranslation::factory()->published(now()->subDay())->create(['title' => 'Live now']);
    ArticleTranslation::factory()->scheduled()->create(['title' => 'Coming soon']);
    ArticleTranslation::factory()->draft()->create(['title' => 'Work in progress']);

    $titles = fn (string $status): array => $this->actingAs($editor)
        ->get(route('admin.articles.index', ['status' => $status]))
        ->assertOk()
        ->viewData('page')['props']['items'];

    expect(array_column($titles('published'), 'title'))->toBe(['Live now'])
        ->and(array_column($titles('scheduled'), 'title'))->toBe(['Coming soon'])
        ->and(array_column($titles('draft'), 'title'))->toBe(['Work in progress']);

    $this->actingAs($editor)->get(route('admin.articles.index', ['status' => 'archived']))
        ->assertSessionHasErrors('status');
});

test('an editor creates a published article with a cover, excerpt and audit entries', function () {
    $this->freezeSecond();
    $editor = User::factory()->editor()->create();
    $cover = MediaAsset::factory()->withVariants()->create();

    $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
        'cover_media_id' => $cover->id,
        'translations' => [
            'en' => articleTranslationInput('Company news', 'company-news', 'published'),
            'pl' => articleTranslationInput('Nowości', 'nowosci'),
            'de' => ['title' => '', 'slug' => '', 'status' => 'draft'],
        ],
    ]);

    $article = Article::query()->sole();
    $response->assertRedirect(route('admin.articles.edit', $article))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.articles.created')]);

    expect($article->cover_media_id)->toBe($cover->id)
        ->and($article->created_by)->toBe($editor->id);

    $en = $article->translations()->where('locale', 'en')->sole();
    expect($en->status)->toBe(PublicationStatus::Published)
        ->and($en->excerpt)->toBe('Short excerpt.')
        ->and($en->published_at?->equalTo(now()))->toBeTrue();

    $pl = $article->translations()->where('locale', 'pl')->sole();
    expect($pl->status)->toBe(PublicationStatus::Draft)
        ->and($pl->published_at)->toBeNull()
        ->and($article->translations()->count())->toBe(2);

    expect(AuditLog::query()->pluck('action')->map->value->all())
        ->toBe([AuditAction::ArticleCreated->value, AuditAction::ArticlePublished->value]);
});

test('a future publication date schedules the article', function () {
    $this->freezeSecond();
    $editor = User::factory()->editor()->create();
    $day = now()->addDays(3)->format('Y-m-d');

    $this->actingAs($editor)->post(route('admin.articles.store'), [
        'translations' => [
            'en' => articleTranslationInput('Launch', 'launch', 'published', ['published_on' => $day]),
        ],
    ])->assertSessionHasNoErrors();

    $translation = ArticleTranslation::query()->sole();
    expect($translation->published_at?->format('Y-m-d H:i:s'))->toBe("{$day} 00:00:00");
    expect(ArticleTranslation::query()->published()->exists())->toBeFalse();
});

test('invalid covers, slugs and dates are rejected without creating anything', function (array $payload, string $errorKey) {
    $editor = User::factory()->editor()->create();
    ArticleTranslation::factory()->published()->create(['slug' => 'taken']);

    $this->actingAs($editor)
        ->post(route('admin.articles.store'), $payload)
        ->assertSessionHasErrors($errorKey);

    expect(Article::query()->count())->toBe(1);
})->with([
    'quarantined cover' => fn () => [
        ['cover_media_id' => MediaAsset::factory()->create()->id, 'translations' => ['en' => articleTranslationInput('A', 'a')]],
        'cover_media_id',
    ],
    'pdf cover' => fn () => [
        ['cover_media_id' => MediaAsset::factory()->clean()->pdf()->create()->id, 'translations' => ['en' => articleTranslationInput('A', 'a')]],
        'cover_media_id',
    ],
    'duplicate slug in the same locale' => fn () => [
        ['translations' => ['en' => articleTranslationInput('A', 'taken')]],
        'translations.en.slug',
    ],
    'invalid slug' => fn () => [
        ['translations' => ['en' => articleTranslationInput('A', 'Not A Slug')]],
        'translations.en.slug',
    ],
    'invalid date' => fn () => [
        ['translations' => ['en' => articleTranslationInput('A', 'a', 'draft', ['published_on' => '31.12.2026'])]],
        'translations.en.published_on',
    ],
    'missing default locale' => fn () => [
        ['translations' => ['pl' => articleTranslationInput('A', 'a')]],
        'translations.en',
    ],
]);

test('the edit screen returns the form state with the cover and publication day', function () {
    $editor = User::factory()->editor()->create();
    $cover = MediaAsset::factory()->withVariants()->create();
    $article = Article::factory()->create(['cover_media_id' => $cover->id]);
    ArticleTranslation::factory()->for($article)->published(Carbon::parse('2026-05-04 10:00:00'))->create(['slug' => 'spring']);

    $this->actingAs($editor)->get(route('admin.articles.edit', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/articles/edit', false)
            ->where('article.id', $article->id)
            ->where('article.coverMediaId', $cover->id)
            ->where('article.translations.en.slug', 'spring')
            ->where('article.translations.en.publishedOn', '2026-05-04')
            ->where('article.translations.pl.title', '')
            ->has('article.translations', 3)
        );
});

test('an update changes the slug of a visible translation, keeps a redirect and audits the cover change', function () {
    $editor = User::factory()->editor()->create();
    $cover = MediaAsset::factory()->withVariants()->create();
    $article = Article::factory()->create();
    $translation = ArticleTranslation::factory()->for($article)->published(now()->subDay())->create(['slug' => 'old-slug']);
    $publishedAt = $translation->published_at;

    $this->actingAs($editor)->put(route('admin.articles.update', $article), [
        'updated_at' => $article->updated_at->toIso8601String(),
        'cover_media_id' => $cover->id,
        'translations' => [
            'en' => articleTranslationInput($translation->title, 'new-slug', 'published', [
                'published_on' => $publishedAt->format('Y-m-d'),
            ]),
        ],
    ])->assertRedirect(route('admin.articles.edit', $article));

    $translation->refresh();
    expect($translation->slug)->toBe('new-slug')
        ->and($translation->published_at?->equalTo($publishedAt))->toBeTrue()
        ->and($article->refresh()->cover_media_id)->toBe($cover->id)
        ->and(ArticleSlugRedirect::query()->where('old_slug', 'old-slug')->value('article_translation_id'))->toBe($translation->id);

    $changes = AuditLog::query()->where('action', AuditAction::ArticleUpdated->value)->sole()->changes;
    expect($changes)->toHaveKeys(['cover_media_id', 'en.slug'])
        ->not->toHaveKey('en.published_at');
});

test('a stale form version is rejected as a conflict without changes', function () {
    $editor = User::factory()->editor()->create();
    $article = Article::factory()->create(['updated_at' => now()]);
    ArticleTranslation::factory()->for($article)->draft()->create(['title' => 'Original', 'slug' => 'original']);

    $this->actingAs($editor)->put(route('admin.articles.update', $article), [
        'updated_at' => now()->subMinute()->toIso8601String(),
        'translations' => ['en' => articleTranslationInput('Changed', 'original')],
    ])->assertSessionHasErrors('conflict');

    expect(ArticleTranslation::query()->sole()->title)->toBe('Original')
        ->and(AuditLog::query()->count())->toBe(0);
});

test('only administrators delete articles; the cover stays in the DAM', function () {
    $editor = User::factory()->editor()->create();
    $admin = User::factory()->admin()->create();
    $cover = MediaAsset::factory()->withVariants()->create();
    $article = Article::factory()->published()->create(['cover_media_id' => $cover->id]);

    $this->actingAs($editor)->delete(route('admin.articles.destroy', $article))->assertForbidden();
    expect(Article::query()->count())->toBe(1);

    $this->actingAs($admin)->delete(route('admin.articles.destroy', $article))
        ->assertRedirect(route('admin.articles.index'));

    expect(Article::query()->count())->toBe(0)
        ->and(ArticleTranslation::query()->count())->toBe(0)
        ->and(MediaAsset::query()->whereKey($cover->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::ArticleDeleted->value)->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('deleting the cover asset keeps the article without a cover', function () {
    $cover = MediaAsset::factory()->withVariants()->create();
    $article = Article::factory()->published()->create(['cover_media_id' => $cover->id]);

    $cover->delete();

    expect($article->refresh()->cover_media_id)->toBeNull();
});
