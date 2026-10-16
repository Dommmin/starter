<?php

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Tag;
use Inertia\Testing\AssertableInertia as Assert;

test('the list filters by tag and offers only tags with visible articles', function () {
    $guides = Tag::factory()->create(['slug' => 'guides', 'name' => ['en' => 'Guides', 'pl' => 'Poradniki']]);
    $news = Tag::factory()->create(['slug' => 'news', 'name' => ['en' => 'News']]);
    $upcoming = Tag::factory()->create(['slug' => 'upcoming', 'name' => ['en' => 'Upcoming']]);

    $tagged = ArticleTranslation::factory()->published(now()->subDay())->create(['title' => 'Tagged', 'slug' => 'tagged']);
    $tagged->article->tags()->attach([$guides->id, $news->id]);
    ArticleTranslation::factory()->published(now()->subDays(2))->create(['title' => 'Untagged']);
    $scheduled = ArticleTranslation::factory()->scheduled()->create(['title' => 'Scheduled']);
    $scheduled->article->tags()->attach([$guides->id, $upcoming->id]);

    $this->get('/articles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('articles/index')
            ->has('items', 2)
            ->where('activeTag', null)
            ->where('tags', [
                ['slug' => 'guides', 'name' => 'Guides', 'url' => url('/articles').'?tag=guides'],
                ['slug' => 'news', 'name' => 'News', 'url' => url('/articles').'?tag=news'],
            ])
            ->where('items.0.title', 'Tagged')
            ->where('items.0.tags.0.slug', 'guides')
            ->where('items.1.tags', [])
        );

    $this->get('/articles?tag=guides')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.title', 'Tagged')
            ->where('activeTag.slug', 'guides')
            ->where('activeTag.name', 'Guides')
        );
});

test('a tag without visible articles shows an empty list and an unknown tag is a 404', function () {
    $upcoming = Tag::factory()->create(['slug' => 'upcoming']);
    ArticleTranslation::factory()->scheduled()->create()->article->tags()->attach($upcoming);

    $this->get('/articles?tag=upcoming')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('items', 0)->where('activeTag.slug', 'upcoming'));

    $this->get('/articles?tag=missing')->assertNotFound();
    $this->get('/articles?tag[]=upcoming')->assertNotFound();
});

test('the article page lists its tags with links to the filtered list', function () {
    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->published(now()->subDay())->create(['slug' => 'hello']);
    $article->tags()->attach(Tag::factory()->create(['slug' => 'news', 'name' => ['en' => 'News']]));

    $this->get('/articles/hello')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('articles/show')
            ->where('tags', [['slug' => 'news', 'name' => 'News', 'url' => url('/articles').'?tag=news']])
        );
});

test('tag names fall back to the fallback locale and then to the slug', function () {
    $tag = Tag::factory()->make(['slug' => 'only-slug', 'name' => ['en' => 'English']]);

    expect($tag->nameIn('pl', 'en'))->toBe('English')
        ->and($tag->nameIn('pl', 'de'))->toBe('only-slug');
});

test('the sitemap never lists tag filters', function () {
    $translation = ArticleTranslation::factory()->published(now()->subDay())->create();
    $translation->article->tags()->attach(Tag::factory()->create(['slug' => 'news']));

    expect($this->get('/sitemap.xml')->assertOk()->getContent())->not->toContain('tag=');
});
