<?php

use App\Actions\Content\CreateContentPreviewLink;
use App\Actions\Home\EnsureHomeSections;
use App\Enums\HomeSectionType;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\ContentPreviewLink;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

function sharedDraftPage(): Page
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->locale('en')->draft()->create(['title' => 'Secret draft', 'slug' => 'secret-draft']);

    return $page->refresh();
}

function sharedLink(Page $page): string
{
    $url = app(CreateContentPreviewLink::class)->handle($page, 'en', 7, User::factory()->editor()->create());
    Auth::logout();

    return $url;
}

test('a guest opens a draft through the link without analytics, caching or a way into the panel', function () {
    $url = sharedLink(sharedDraftPage());

    $response = $this->get($url)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $assert) => $assert
            ->component('pages/show')
            ->where('title', 'Secret draft')
            ->where('preview.editUrl', null)
            ->where('consent', null));

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(ContentPreviewLink::query()->sole()->token_hash)->not->toContain(basename($url));
});

test('the link shows the sections of a page built from sections', function () {
    $page = sharedDraftPage();
    $page->update(['layout_mode' => 'sections']);
    $page->sections()->create(['locale' => 'en', 'type' => HomeSectionType::Cta, 'position' => 1, 'enabled' => true, 'content' => EnsureHomeSections::placeholder(HomeSectionType::Cta, 'en')]);

    $this->get(sharedLink($page))->assertInertia(fn (Assert $assert) => $assert->has('sections', 1));
});

test('an expired, revoked or trashed link is the same 404 and is not logged', function (Closure $break) {
    $page = sharedDraftPage();
    $url = sharedLink($page);
    $break($page);

    $this->get($url)->assertNotFound();

    expect(NotFoundLog::query()->count())->toBe(0);
})->with([
    // A Closure parameter receives these as they are (Pest does not resolve them).
    'expired' => fn (Page $page) => ContentPreviewLink::query()->update(['expires_at' => now()->subMinute()]),
    'revoked' => fn (Page $page) => ContentPreviewLink::query()->delete(),
    'trashed' => fn (Page $page) => $page->delete(),
]);

test('the link works while the coming soon page is on', function () {
    $url = sharedLink(sharedDraftPage());
    SiteSetting::factory()->create(['coming_soon' => true]);

    $this->get($url)->assertInertia(fn (Assert $assert) => $assert->component('pages/show'));
});

test('an editor creates a link shown once and revokes it', function () {
    $editor = User::factory()->editor()->create();
    $page = sharedDraftPage();

    $this->actingAs($editor)->post(route('admin.pages.preview-links.store', $page), ['locale' => 'en', 'days' => 7])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('sharedPreviewUrl');

    $this->actingAs($editor)->delete(route('admin.pages.preview-links.destroy', [$page, ContentPreviewLink::query()->sole()->id]));

    expect(ContentPreviewLink::query()->count())->toBe(0);
});

test('a link needs a saved language, an offered lifetime and stays under the limit', function () {
    $editor = User::factory()->editor()->create();
    $page = sharedDraftPage();

    $this->actingAs($editor)->post(route('admin.pages.preview-links.store', $page), ['locale' => 'pl', 'days' => 365])
        ->assertSessionHasErrors(['locale', 'days']);

    ContentPreviewLink::factory()->count(CreateContentPreviewLink::MAX_ACTIVE)->create(['subject_id' => $page->id]);

    $this->actingAs($editor)->post(route('admin.pages.preview-links.store', $page), ['locale' => 'en', 'days' => 7])
        ->assertSessionHasErrors('preview_link');

    expect(ContentPreviewLink::query()->count())->toBe(CreateContentPreviewLink::MAX_ACTIVE);
});

test('a viewer cannot create or revoke links', function () {
    $viewer = User::factory()->viewer()->create();
    $page = sharedDraftPage();
    $link = ContentPreviewLink::factory()->create(['subject_id' => $page->id]);

    $this->actingAs($viewer)->post(route('admin.pages.preview-links.store', $page), ['locale' => 'en', 'days' => 7])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.pages.preview-links.destroy', [$page, $link->id]))->assertForbidden();

    expect(ContentPreviewLink::query()->count())->toBe(1);
});

test('an article draft is shared the same way', function () {
    $editor = User::factory()->editor()->create();
    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->locale('en')->create(['title' => 'Draft article', 'status' => PublicationStatus::Draft, 'published_at' => null]);

    $response = $this->actingAs($editor)->post(route('admin.articles.preview-links.store', $article), ['locale' => 'en', 'days' => 1])
        ->assertSessionHasNoErrors();
    $url = $response->baseResponse->getSession()->get('inertia.flash_data')['sharedPreviewUrl'];
    Auth::logout();

    $this->get($url)->assertInertia(fn (Assert $assert) => $assert
        ->component('articles/show')
        ->where('title', 'Draft article')
        ->where('preview.editUrl', null));
});
