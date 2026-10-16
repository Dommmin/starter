<?php

use App\Models\SiteSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('guests see the coming soon page with noindex while the switch is on', function (string $url) {
    SiteSetting::factory()->create(['coming_soon' => true, 'contact_email' => 'hello@acme.test']);

    $this->get($url)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('public/coming-soon')
            ->where('contactEmail', 'hello@acme.test')
            ->where('seo.noindex', true)
        );
})->with(['home' => '/', 'localized home' => '/pl', 'missing page' => '/any-page']);

test('panel users see the whole site while the coming soon page is on', function () {
    SiteSetting::factory()->create(['coming_soon' => true]);

    $this->actingAs(User::factory()->editor()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->component('welcome'));
});

test('sign-in stays reachable and robots.txt blocks crawling while the coming soon page is on', function () {
    $this->app['env'] = 'production';
    SiteSetting::factory()->create(['coming_soon' => true]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->component('auth/login'));

    expect($this->get('/robots.txt')->assertOk()->getContent())->toContain("Disallow: /\n");
});

test('the public site is indexable again once both switches are off', function () {
    $this->app['env'] = 'production';
    SiteSetting::factory()->create(['coming_soon' => false, 'noindex' => false]);

    $response = $this->get('/')->assertOk();

    expect($response->headers->has('X-Robots-Tag'))->toBeFalse()
        ->and($this->get('/robots.txt')->getContent())->toContain('Allow: /');
    $response->assertInertia(fn (Assert $inertia) => $inertia->component('welcome')->where('seo.noindex', false));
});

test('the noindex switch alone keeps the site visible but not indexable', function () {
    SiteSetting::factory()->create(['noindex' => true]);

    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia->component('welcome')->where('seo.noindex', true));
});
