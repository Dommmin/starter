<?php

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;
use App\Models\MediaAsset;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the list shows only visible articles of the current locale, newest first', function () {
    ArticleTranslation::factory()->published(now()->subDays(2))->create(['title' => 'Older']);
    ArticleTranslation::factory()->published(now()->subDay())->create(['title' => 'Newer', 'slug' => 'newer']);
    ArticleTranslation::factory()->draft()->create(['title' => 'Draft']);
    ArticleTranslation::factory()->scheduled()->create(['title' => 'Scheduled']);
    ArticleTranslation::factory()->locale('pl')->published()->create(['title' => 'Polski']);

    $this->get('/articles')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('articles/index', false)
            ->has('items', 2)
            ->where('items.0.title', 'Newer')
            ->where('items.0.url', url('/articles/newer'))
            ->where('items.1.title', 'Older')
            ->where('pagination.total', 2)
            ->where('locale', 'en')
            ->has('i18n.messages.articles.title')
            ->missing('i18n.messages.admin')
        );

    $this->get('/pl/articles')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.title', 'Polski')
        );
});

test('a page number beyond the last page is not found', function () {
    ArticleTranslation::factory()->published()->create();

    $this->get('/articles?page=2')->assertNotFound();
});

test('a visible article renders sanitized html, its cover and alternates of visible translations', function () {
    $cover = MediaAsset::factory()->withVariants()->create(['alt' => 'Team photo']);
    $article = Article::factory()->create(['cover_media_id' => $cover->id]);
    ArticleTranslation::factory()->for($article)->published()->create([
        'title' => 'Company news',
        'slug' => 'company-news',
        'excerpt' => 'What happened this month.',
        'meta_description' => null,
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']]],
        ]],
    ]);
    ArticleTranslation::factory()->for($article)->locale('pl')->published()->create(['slug' => 'nowosci']);
    ArticleTranslation::factory()->for($article)->locale('de')->scheduled()->create(['slug' => 'neuigkeiten']);

    $this->get('/articles/company-news')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('articles/show', false)
            ->where('title', 'Company news')
            ->where('metaDescription', 'What happened this month.')
            ->where('bodyHtml', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>')
            ->where('coverAlt', 'Team photo')
            ->where('cover.width', 640)
            ->where('listUrl', url('/articles'))
            ->where('alternates', [
                'en' => url('/articles/company-news'),
                'pl' => url('/pl/articles/nowosci'),
                'x-default' => url('/articles/company-news'),
            ])
            ->where('i18n.alternateUrls', [
                'en' => url('/articles/company-news'),
                'pl' => url('/pl/articles/nowosci'),
                'x-default' => url('/articles/company-news'),
            ])
        );
});

test('drafts, scheduled articles, other locales and unknown slugs are indistinguishable 404s', function () {
    ArticleTranslation::factory()->draft()->create(['slug' => 'draft']);
    ArticleTranslation::factory()->scheduled()->create(['slug' => 'scheduled']);
    ArticleTranslation::factory()->locale('pl')->published()->create(['slug' => 'tylko-po-polsku']);

    $this->get('/articles/draft')->assertNotFound();
    $this->get('/articles/scheduled')->assertNotFound();
    $this->get('/articles/tylko-po-polsku')->assertNotFound();
    $this->get('/articles/missing')->assertNotFound();
});

test('a former slug redirects once to the current URL, but not to a hidden translation', function () {
    $translation = ArticleTranslation::factory()->published()->create(['slug' => 'current']);
    ArticleSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'former', 'article_translation_id' => $translation->id]);
    $draft = ArticleTranslation::factory()->draft()->create(['slug' => 'hidden']);
    ArticleSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'former-hidden', 'article_translation_id' => $draft->id]);

    $this->get('/articles/former')->assertStatus(301)->assertRedirect(url('/articles/current'));
    $this->get('/articles/former-hidden')->assertNotFound();
});

test('a page slug named like the article section is rejected', function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => ['title' => 'Articles', 'slug' => 'articles', 'status' => 'draft']],
    ])->assertSessionHasErrors('translations.en.slug');
});

test('an article without meta description or excerpt is described by its shortened first paragraph', function () {
    $paragraph = str_repeat('Lorem ipsum dolor sit amet. ', 10);
    ArticleTranslation::factory()->published()->create([
        'slug' => 'no-summary',
        'excerpt' => null,
        'meta_description' => null,
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]],
        ]],
    ]);

    $this->get('/articles/no-summary')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('metaDescription', str_repeat('Lorem ipsum dolor sit amet. ', 5).'Lorem ipsum dolor…')
        );
});
