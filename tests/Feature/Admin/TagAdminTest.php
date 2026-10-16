<?php

use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tagPayload(array $overrides = []): array
{
    return [
        'slug' => 'guides',
        'name' => ['en' => 'Guides', 'pl' => 'Poradniki', 'de' => ''],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taggedArticlePayload(array $overrides = []): array
{
    return [
        'translations' => [
            'en' => [
                'title' => 'Tagged',
                'slug' => 'tagged',
                'excerpt' => null,
                'meta_description' => null,
                'body' => null,
                'status' => 'draft',
                'published_on' => null,
            ],
        ],
        ...$overrides,
    ];
}

test('an editor creates and renames a tag, both audited', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.tags.store'), tagPayload())->assertSessionHasNoErrors();

    $tag = Tag::query()->sole();
    expect($tag->slug)->toBe('guides')
        ->and($tag->name)->toBe(['en' => 'Guides', 'pl' => 'Poradniki']);

    $this->actingAs($editor)
        ->put(route('admin.tags.update', $tag), tagPayload([
            'updated_at' => $tag->updated_at?->toIso8601String(),
            'name' => ['en' => 'How-to', 'pl' => 'Poradniki'],
        ]))
        ->assertSessionHasNoErrors();

    expect($tag->refresh()->name['en'])->toBe('How-to')
        ->and(AuditLog::query()->orderBy('id')->pluck('action')->all())
        ->toBe([AuditAction::TagCreated, AuditAction::TagUpdated]);
});

test('the list shows the name in the panel locale and the number of articles', function () {
    $editor = User::factory()->editor()->create();
    $tag = Tag::factory()->create(['slug' => 'news', 'name' => ['en' => 'News', 'pl' => 'Aktualności']]);
    $tag->articles()->attach(Article::factory()->count(2)->create());

    $this->actingAs($editor)->get(route('admin.tags.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tags/index', false)
            ->where('canCreate', true)
            ->where('items.0.slug', 'news')
            ->where('items.0.name', 'News')
            ->where('items.0.articlesCount', 2)
        );
});

test('the slug is lowercase, unique and the default language name is required', function (array $overrides, string $error) {
    Tag::factory()->create(['slug' => 'taken']);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.tags.store'), tagPayload($overrides))->assertSessionHasErrors($error);

    expect(Tag::query()->count())->toBe(1);
})->with([
    'uppercase slug' => [['slug' => 'Guides'], 'slug'],
    'slug with spaces' => [['slug' => 'how to'], 'slug'],
    'taken slug' => [['slug' => 'taken'], 'slug'],
    'missing default name' => [['name' => ['en' => '', 'pl' => 'Poradniki']], 'name.en'],
]);

test('a stale version is a conflict and changes nothing', function () {
    $editor = User::factory()->editor()->create();
    $tag = Tag::factory()->create(['slug' => 'news']);

    $this->actingAs($editor)
        ->put(route('admin.tags.update', $tag), tagPayload(['updated_at' => '2020-01-01T00:00:00+00:00']))
        ->assertSessionHasErrors('conflict');

    expect($tag->refresh()->slug)->toBe('news');
});

test('only an administrator deletes a tag, which removes it from its articles', function () {
    $editor = User::factory()->editor()->create();
    $admin = User::factory()->admin()->create();
    $article = Article::factory()->create();
    $tag = Tag::factory()->create();
    $tag->articles()->attach($article);

    $this->actingAs($editor)->delete(route('admin.tags.destroy', $tag))->assertForbidden();
    expect(Tag::query()->count())->toBe(1);

    $this->actingAs($admin)->delete(route('admin.tags.destroy', $tag))->assertRedirect(route('admin.tags.index'));

    expect(Tag::query()->count())->toBe(0)
        ->and(Article::query()->count())->toBe(1)
        ->and($article->tags()->count())->toBe(0)
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::TagDeleted);
});

test('users without a panel role cannot reach the tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $this->actingAs($user)->get(route('admin.tags.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.tags.store'), tagPayload(['slug' => 'other']))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.tags.destroy', $tag))->assertForbidden();

    expect(Tag::query()->count())->toBe(1);
});

test('the article editor lists the tags and saves the selection with an audit entry', function () {
    $editor = User::factory()->editor()->create();
    $news = Tag::factory()->create(['slug' => 'news', 'name' => ['en' => 'News']]);
    $guides = Tag::factory()->create(['slug' => 'guides', 'name' => ['en' => 'Guides']]);

    $this->actingAs($editor)->get(route('admin.articles.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tagOptions', [['id' => $guides->id, 'label' => 'Guides'], ['id' => $news->id, 'label' => 'News']])
            ->where('maxTags', 10)
        );

    $this->actingAs($editor)
        ->post(route('admin.articles.store'), taggedArticlePayload(['tag_ids' => [$news->id, $guides->id]]))
        ->assertSessionHasNoErrors();

    $article = Article::query()->sole();
    expect($article->tags()->pluck('slug')->all())->toBe(['guides', 'news'])
        ->and(AuditLog::query()->sole()->changes['tags'] ?? null)->toBe(['old' => null, 'new' => ['guides', 'news']]);

    $this->actingAs($editor)->get(route('admin.articles.edit', $article))
        ->assertInertia(fn (Assert $page) => $page->where('article.tagIds', [$guides->id, $news->id]));
});

test('an update replaces the tags, and a request without tag_ids keeps them', function () {
    $editor = User::factory()->editor()->create();
    $news = Tag::factory()->create(['slug' => 'news']);
    $guides = Tag::factory()->create(['slug' => 'guides']);
    $this->actingAs($editor)->post(route('admin.articles.store'), taggedArticlePayload(['tag_ids' => [$news->id]]));
    $article = Article::query()->sole();

    $this->actingAs($editor)
        ->put(route('admin.articles.update', $article), taggedArticlePayload([
            'updated_at' => $article->refresh()->updated_at?->toIso8601String(),
            'tag_ids' => [$guides->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($article->tags()->pluck('slug')->all())->toBe(['guides'])
        ->and(AuditLog::query()->latest('id')->first()?->changes['tags'] ?? null)->toBe(['old' => ['news'], 'new' => ['guides']]);

    $this->actingAs($editor)
        ->put(route('admin.articles.update', $article), taggedArticlePayload([
            'updated_at' => $article->refresh()->updated_at?->toIso8601String(),
        ]))
        ->assertSessionHasNoErrors();

    expect($article->tags()->pluck('slug')->all())->toBe(['guides']);
});

test('unknown tags and more than the maximum are rejected', function () {
    $editor = User::factory()->editor()->create();
    $tags = Tag::factory()->count(11)->create();

    $this->actingAs($editor)
        ->post(route('admin.articles.store'), taggedArticlePayload(['tag_ids' => [999999]]))
        ->assertSessionHasErrors('tag_ids.0');
    $this->actingAs($editor)
        ->post(route('admin.articles.store'), taggedArticlePayload(['tag_ids' => $tags->modelKeys()]))
        ->assertSessionHasErrors('tag_ids');

    expect(Article::query()->count())->toBe(0);
});
