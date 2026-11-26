<?php

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Consent\CookieConsent;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'consent.version' => 2,
        'services.plausible.domain' => 'example.com',
        'services.plausible.script_url' => 'https://plausible.io/js/script.js',
    ]);
});

/**
 * Stored choice in the browser format: version, offered and granted categories.
 *
 * @param  list<string>  $offered
 * @param  list<string>  $granted
 */
function consentCookie(int $version, array $offered, array $granted): string
{
    return (string) json_encode(['v' => $version, 'o' => $offered, 'g' => $granted]);
}

test('a visitor without a choice gets the offered categories and no decision', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('consent.version', 2)
            ->where('consent.cookieName', CookieConsent::COOKIE)
            ->where('consent.lifetimeDays', 365)
            ->where('consent.categories', ['analytics'])
            ->where('consent.granted', null)
            ->where('consent.plausible', ['domain' => 'example.com', 'scriptUrl' => 'https://plausible.io/js/script.js'])
        );
});

test('a valid choice of the current version is shared', function (array $granted) {
    $this->withUnencryptedCookie(CookieConsent::COOKIE, consentCookie(2, ['analytics'], $granted))
        ->get('/')
        ->assertInertia(fn (Assert $inertia) => $inertia->where('consent.granted', $granted));
})->with([
    'accepted' => [['analytics']],
    'rejected' => [[]],
]);

test('an outdated, incomplete or malformed choice shows the banner again', function (string $cookie) {
    $this->withUnencryptedCookie(CookieConsent::COOKIE, $cookie)
        ->get('/')
        ->assertInertia(fn (Assert $inertia) => $inertia->where('consent.granted', null));
})->with([
    'older version' => [consentCookie(1, ['analytics'], ['analytics'])],
    'category offered later' => [consentCookie(2, [], [])],
    'not JSON' => ['analytics'],
    'wrong shape' => ['{"v":2,"o":"analytics","g":[]}'],
    'too long' => [str_repeat('a', 600)],
]);

test('categories without a configured service are not offered', function () {
    config(['services.plausible.domain' => null]);

    $this->withUnencryptedCookie(CookieConsent::COOKIE, consentCookie(2, ['analytics'], ['analytics']))
        ->get('/')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('consent.categories', [])
            ->where('consent.granted', [])
            ->where('consent.plausible', null)
        );
});

test('the plausible domain from the panel wins over the environment', function () {
    SiteSetting::factory()->create(['plausible_domain' => 'panel.example']);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('consent.categories', ['analytics'])
        ->where('consent.plausible', ['domain' => 'panel.example', 'scriptUrl' => 'https://plausible.io/js/script.js'])
    );
});

test('an empty plausible domain in the panel falls back to the environment', function () {
    SiteSetting::factory()->create(['plausible_domain' => null]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('consent.plausible.domain', 'example.com')
    );
});

test('without a domain in the panel and the environment no analytics is loaded', function () {
    config(['services.plausible.domain' => null]);
    SiteSetting::factory()->create(['plausible_domain' => null]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('consent.categories', [])
        ->where('consent.plausible', null)
    );
});

test('a script URL without https loads no analytics', function () {
    config(['services.plausible.script_url' => 'http://plausible.example/js/script.js']);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('consent.categories', [])
        ->where('consent.plausible', null)
    );
});

test('the banner links the first published policy page of the locale', function () {
    config(['consent.policy_slugs.en' => ['cookie-policy', 'privacy-policy']]);
    PageTranslation::factory()->for(Page::factory())->published()->locale('en')->create(['slug' => 'privacy-policy']);
    PageTranslation::factory()->for(Page::factory())->locale('en')->create(['slug' => 'cookie-policy']);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('consent.policyUrl', url('/privacy-policy'))
    );
});

test('the admin panel does not get the consent prop', function () {
    config(['fortify.require_two_factor_for_admin' => false]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->missing('consent'));
});
