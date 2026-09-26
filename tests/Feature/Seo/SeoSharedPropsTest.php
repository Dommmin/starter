<?php

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the shared seo prop exposes site defaults and the canonical url without query string', function () {
    config([
        'seo.site_name' => 'Starter',
        'seo.default_image' => '/images/og.png',
        'seo.organization.name' => 'Acme',
        'seo.organization.url' => null,
        'seo.organization.logo' => 'https://cdn.example.test/logo.png',
    ]);

    $this->get('/pl?utm_source=newsletter')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('welcome')
            ->where('seo', [
                'siteName' => 'Starter',
                'canonical' => url('/pl'),
                'defaultImage' => url('/images/og.png'),
                'organization' => [
                    'name' => 'Acme',
                    'url' => url('/'),
                    'logo' => 'https://cdn.example.test/logo.png',
                ],
            ])
            ->where('i18n.alternateUrls', [
                'en' => url('/'),
                'pl' => url('/pl'),
                'de' => url('/de'),
                'x-default' => url('/'),
            ])
        );
});

test('a page shares its own translated alternates instead of substituting the slug', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'about-us']);
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['slug' => 'o-nas']);

    $this->get('/pl/o-nas?ref=1')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('pages/show', false)
            ->where('seo.canonical', url('/pl/o-nas'))
            ->where('i18n.alternateUrls', [
                'en' => url('/about-us'),
                'pl' => url('/pl/o-nas'),
                'x-default' => url('/about-us'),
            ])
        );
});

test('public pages are indexable and carry no robots header', function () {
    $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
});

test('authentication pages are not indexable', function (string $path) {
    $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['/login', '/pl/login', '/forgot-password']);

test('the admin panel and account settings are not indexable', function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get(route('admin.index'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
