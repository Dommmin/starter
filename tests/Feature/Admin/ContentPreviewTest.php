<?php

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

function draftPage(): Page
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->draft()->create([
        'title' => 'Upcoming offer',
        'slug' => 'upcoming-offer',
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Secret draft body']]],
        ]],
    ]);

    return $page;
}

function pagePreviewUrl(Page $page, string $locale = 'en', int $minutes = 30): string
{
    return URL::temporarySignedRoute('admin.pages.preview', now()->addMinutes($minutes), [
        'page' => $page,
        'contentLocale' => $locale,
    ]);
}

test('editors and administrators preview a draft page with the public screen, uncached and not indexable', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $page = draftPage();

    $response = $this->actingAs($user)->get(pagePreviewUrl($page));

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('pages/show', false)
            ->where('title', 'Upcoming offer')
            ->where('bodyHtml', '<p>Secret draft body</p>')
            ->where('alternates', [])
            ->where('preview.state', 'draft')
            ->where('preview.editUrl', route('admin.pages.edit', $page))
            ->where('i18n.area', 'public')
            ->where('i18n.alternateUrls', [])
            ->has('i18n.messages.preview.titleUnpublished')
            ->missing('i18n.messages.admin')
        );

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store')->toContain('private');
    expect(PageTranslation::query()->sole()->status)->toBe(PublicationStatus::Draft);
})->with(['editor', 'admin']);

test('the preview renders the chosen language with its public catalog and html language', function () {
    $user = User::factory()->editor()->create();
    $page = draftPage();
    PageTranslation::factory()->for($page)->locale('de')->draft()->create(['title' => 'Kommendes Angebot']);

    $this->actingAs($user)->get(pagePreviewUrl($page, 'de'))
        ->assertOk()
        ->assertSee('lang="de"', false)
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('title', 'Kommendes Angebot')
            ->where('locale', 'de')
            ->where('i18n.locale', 'de')
        );
});

test('a scheduled article is previewed with its planned publication date', function () {
    $user = User::factory()->editor()->create();
    $article = Article::factory()->create();
    $translation = ArticleTranslation::factory()->for($article)->scheduled()->create(['title' => 'Coming soon']);

    $url = URL::temporarySignedRoute('admin.articles.preview', now()->addMinutes(30), [
        'article' => $article,
        'contentLocale' => 'en',
    ]);

    $this->actingAs($user)->get($url)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('articles/show', false)
            ->where('title', 'Coming soon')
            ->where('preview.state', 'scheduled')
            ->where('preview.publishAt', $translation->published_at?->toIso8601String())
            ->where('preview.editUrl', route('admin.articles.edit', $article))
        );

    $this->get('/articles/'.$translation->slug)->assertNotFound();
});

test('the public address of a previewed draft stays not found', function () {
    $user = User::factory()->editor()->create();
    $page = draftPage();

    $this->actingAs($user)->get(pagePreviewUrl($page))->assertOk();

    $this->get('/upcoming-offer')->assertNotFound();
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('upcoming-offer');
});

test('guests are sent to the login page', function () {
    $this->get(pagePreviewUrl(draftPage()))->assertRedirect(route('login'));
});

test('users without panel access are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(pagePreviewUrl(draftPage()))->assertForbidden();
});

test('a missing, expired or altered signature is forbidden', function () {
    $user = User::factory()->editor()->create();
    $page = draftPage();
    PageTranslation::factory()->for($page)->locale('de')->draft()->create();
    $other = draftPageWithSlug('other-draft');

    $this->actingAs($user)
        ->get(route('admin.pages.preview', ['page' => $page, 'contentLocale' => 'en']))
        ->assertForbidden();

    $expired = pagePreviewUrl($page);
    $this->travel(31)->minutes();
    $this->actingAs($user)->get($expired)->assertForbidden();
    $this->travelBack();

    $valid = pagePreviewUrl($page);
    $this->actingAs($user)->get(str_replace('/preview/en?', '/preview/de?', $valid))->assertForbidden();
    $this->actingAs($user)->get(str_replace("/pages/{$page->id}/", "/pages/{$other->id}/", $valid))->assertForbidden();
    $this->actingAs($user)->get($valid)->assertOk();
});

test('an unknown language or a missing translation is not found', function (string $locale) {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)->get(pagePreviewUrl(draftPage(), $locale))->assertNotFound();
})->with(['unknown language' => 'xx', 'no translation' => 'pl']);

test('the editor receives signed preview links of saved translations only', function () {
    $user = User::factory()->editor()->create();
    $page = draftPage();

    $this->actingAs($user)->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('previewUrls', 1)
            ->where('previewUrls.en', fn (string $url) => str_contains($url, "/admin/pages/{$page->id}/preview/en?")
                && str_contains($url, 'signature=')
                && str_contains($url, 'expires='))
        );

    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->locale('pl')->draft()->create();

    $this->actingAs($user)->get(route('admin.articles.edit', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('previewUrls', 1)
            ->where('previewUrls.pl', fn (string $url) => str_contains($url, "/admin/articles/{$article->id}/preview/pl?"))
        );
});

function draftPageWithSlug(string $slug): Page
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->draft()->create(['slug' => $slug]);

    return $page;
}
