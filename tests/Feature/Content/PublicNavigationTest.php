<?php

use App\Enums\MenuLocation;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Services\Navigation\PublicNavigation;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * A page with a published translation in each given locale (slug = "{title}-{locale}").
 *
 * @param  list<string>  $locales
 */
function navigationPage(string $title, array $locales = ['en']): Page
{
    $page = Page::factory()->create();
    foreach ($locales as $locale) {
        PageTranslation::factory()->for($page)->published()->locale($locale)->create([
            'title' => "{$title} {$locale}",
            'slug' => "{$title}-{$locale}",
        ]);
    }

    return $page;
}

test('the home page shares the resolved header and footer menus', function () {
    $page = navigationPage('about');
    $group = MenuItem::factory()->in(MenuLocation::Footer, 'en')->group()->create(['label' => 'Company', 'position' => 1]);
    MenuItem::factory()->childOf($group)->page($page)->create();
    MenuItem::factory()->anchor('features')->create(['label' => 'Features', 'position' => 1]);
    MenuItem::factory()->articleIndex()->create(['label' => 'Blog', 'position' => 2]);
    MenuItem::factory()->create(['label' => 'Docs', 'url' => 'https://docs.example.com', 'open_in_new_tab' => true, 'position' => 3]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('welcome', false)
            ->where('navigation.header', [
                ['id' => MenuItem::query()->where('label', 'Features')->value('id'), 'label' => 'Features', 'href' => url('/').'#features', 'kind' => 'anchor', 'newTab' => false, 'children' => []],
                ['id' => MenuItem::query()->where('label', 'Blog')->value('id'), 'label' => 'Blog', 'href' => route('articles.index'), 'kind' => 'internal', 'newTab' => false, 'children' => []],
                ['id' => MenuItem::query()->where('label', 'Docs')->value('id'), 'label' => 'Docs', 'href' => 'https://docs.example.com', 'kind' => 'external', 'newTab' => true, 'children' => []],
            ])
            ->where('navigation.footer.0.kind', 'group')
            ->where('navigation.footer.0.label', 'Company')
            ->missing('navigation.footer.0.href')
            ->where('navigation.footer.0.children.0.label', 'about en')
            ->where('navigation.footer.0.children.0.href', route('pages.show', ['slug' => 'about-en']))
        );
});

test('items exclude target ids, statuses and other internal fields', function () {
    MenuItem::factory()->page(navigationPage('about'))->create();

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->has('navigation.header.0', fn (Assert $item) => $item
            ->hasAll(['id', 'label', 'href', 'kind', 'newTab', 'children'])
            ->missing('page_id')
            ->missing('pageId')
            ->missing('status')
            ->missing('type')
        )
    );
});

test('an empty label falls back to the translation title and hrefs follow the locale', function () {
    $page = navigationPage('about', ['en', 'pl']);
    MenuItem::factory()->page($page)->create();
    MenuItem::factory()->in(MenuLocation::Header, 'pl')->page($page)->create(['position' => 1]);
    MenuItem::factory()->in(MenuLocation::Header, 'pl')->articleIndex()->create(['label' => 'Artykuły', 'position' => 2]);
    MenuItem::factory()->in(MenuLocation::Header, 'pl')->anchor('features')->create(['label' => 'Funkcje', 'position' => 3]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header.0.label', 'about en')
        ->where('navigation.header.0.href', url('/about-en'))
        ->has('navigation.header', 1)
    );

    $this->get('/pl')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header.0.label', 'about pl')
        ->where('navigation.header.0.href', url('/pl/about-pl'))
        ->where('navigation.header.1.href', url('/pl/articles'))
        ->where('navigation.header.2.href', url('/pl').'#features')
    );
});

test('unpublished, scheduled, untranslated and deleted targets are hidden', function () {
    $draftPage = Page::factory()->create();
    PageTranslation::factory()->for($draftPage)->draft()->create();
    $germanOnly = navigationPage('nur', ['de']);
    $scheduled = Article::factory()->create();
    ArticleTranslation::factory()->for($scheduled)->scheduled()->create();
    $draftArticle = Article::factory()->create();
    ArticleTranslation::factory()->for($draftArticle)->draft()->create();
    $deleted = navigationPage('gone');
    $visibleArticle = Article::factory()->create();
    ArticleTranslation::factory()->for($visibleArticle)->published(now()->subDay())->create(['title' => 'Visible article', 'slug' => 'visible-article']);

    MenuItem::factory()->page($draftPage)->create();
    MenuItem::factory()->page($germanOnly)->create();
    MenuItem::factory()->article($scheduled)->create();
    MenuItem::factory()->article($draftArticle)->create();
    MenuItem::factory()->page($deleted)->create();
    MenuItem::factory()->anchor('team', $draftPage)->create(['label' => 'Team']);
    MenuItem::factory()->article($visibleArticle)->create();
    $deleted->delete();

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->has('navigation.header', 1)
        ->where('navigation.header.0.label', 'Visible article')
        ->where('navigation.header.0.href', route('articles.show', ['slug' => 'visible-article']))
    );
});

test('anchors on a deleted page disappear with it and home anchors stay', function () {
    $admin = User::factory()->admin()->create();
    $page = navigationPage('team');
    $pageAnchor = MenuItem::factory()->anchor('members', $page)->create(['label' => 'Members', 'position' => 1]);
    $child = MenuItem::factory()->childOf($pageAnchor)->create(['label' => 'Child']);
    $pageLink = MenuItem::factory()->page($page)->create(['label' => 'Team page', 'position' => 2]);
    MenuItem::factory()->anchor('features')->create(['label' => 'Features', 'position' => 3]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header.0.href', url('/team-en').'#members')
    );

    $this->actingAs($admin)->delete(route('admin.pages.destroy', $page))->assertRedirect();

    expect(MenuItem::query()->whereKey([$pageAnchor->id, $child->id])->exists())->toBeFalse()
        ->and($pageLink->fresh()?->page_id)->toBeNull();

    auth()->logout();

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->has('navigation.header', 1)
        ->where('navigation.header.0.label', 'Features')
        ->where('navigation.header.0.href', url('/').'#features')
    );
});

test('a hidden parent hides its branch and an empty group is hidden', function () {
    $draftPage = Page::factory()->create();
    PageTranslation::factory()->for($draftPage)->draft()->create();
    $hiddenParent = MenuItem::factory()->page($draftPage)->create(['position' => 1]);
    MenuItem::factory()->childOf($hiddenParent)->create(['label' => 'Orphan']);
    $emptyGroup = MenuItem::factory()->group()->create(['label' => 'Empty', 'position' => 2]);
    MenuItem::factory()->childOf($emptyGroup)->page($draftPage)->create();

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header', [])
        ->where('navigation.footer', [])
    );
});

test('unsafe stored urls are dropped on output', function () {
    MenuItem::factory()->create(['url' => 'javascript:alert(1)']);
    MenuItem::factory()->create(['url' => '//evil.com/path']);
    MenuItem::factory()->create(['url' => 'data:text/html,hi']);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header', [])
    );
});

test('the menu cache is cleared after a slug or status change', function () {
    $page = navigationPage('about');
    MenuItem::factory()->page($page)->create();
    $key = PublicNavigation::cacheKey(MenuLocation::Header, 'en');

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header.0.href', url('/about-en'))
    );
    expect(Cache::has($key))->toBeTrue();

    $translation = $page->translations()->sole();
    $translation->update(['slug' => 'renamed']);
    expect(Cache::has($key))->toBeFalse();

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header.0.href', url('/renamed'))
    );

    $translation->update(['status' => 'draft']);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header', [])
    );
});

test('admin menu changes clear the cache', function () {
    $admin = User::factory()->admin()->create();
    $this->get('/');
    $key = PublicNavigation::cacheKey(MenuLocation::Header, 'en');
    expect(Cache::has($key))->toBeTrue();

    $this->actingAs($admin)->post(route('admin.navigation.store'), [
        'location' => 'header', 'locale' => 'en', 'type' => 'article_index', 'label' => 'Blog', 'open_in_new_tab' => false,
    ])->assertSessionHasNoErrors();

    expect(Cache::has($key))->toBeFalse();
});

test('a scheduled article limits the cache lifetime to its publication', function () {
    $this->freezeSecond();
    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->published(now()->addMinutes(10))->create();
    MenuItem::factory()->article($article)->create();

    $navigation = app(PublicNavigation::class);
    expect($navigation->for(MenuLocation::Header, 'en'))->toBe([]);

    $this->travel(10)->minutes();
    $this->travel(1)->seconds();

    expect($navigation->for(MenuLocation::Header, 'en'))->toHaveCount(1);
});

test('the navigation prop is not shared with the admin area', function () {
    $admin = User::factory()->admin()->create();
    MenuItem::factory()->create();

    $this->actingAs($admin)->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->missing('navigation'));
});

test('the seeder creates default menus once per public locale', function () {
    $privacy = navigationPage('privacy', ['en', 'pl']);
    $privacy->translations()->where('locale', 'en')->update(['slug' => NavigationMenuSeeder::PRIVACY_POLICY_SLUG]);

    $this->seed(NavigationMenuSeeder::class);
    $this->seed(NavigationMenuSeeder::class);

    expect(NavigationMenuSeeder::defaults('de', NavigationMenuSeeder::privacyPolicyPageId()))->not->toHaveKey('footer')
        ->and(NavigationMenuSeeder::defaults('pl', $privacy->id)['footer'][0]['page_id'] ?? null)->toBe($privacy->id);

    expect(MenuItem::query()->where('location', 'header')->count())->toBe(6)
        ->and(MenuItem::query()->where('location', 'footer')->pluck('locale')->sort()->values()->all())->toBe(['en', 'pl']);

    $this->get('/pl')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('navigation.header.0.label', __('common.nav.features', [], 'pl'))
        ->where('navigation.header.1.href', url('/pl/articles'))
        ->where('navigation.footer.0.label', 'privacy pl')
    );
});
