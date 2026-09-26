<?php

use App\Models\Page;
use App\Models\PageTranslation;

test('the sitemap lists the home page in every public locale with reciprocal alternates', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600');

    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();

    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->registerXPathNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

    $locs = array_map('strval', $xml->xpath('//s:url/s:loc'));
    expect($locs)->toBe([url('/'), url('/pl'), url('/de')]);

    $homeAlternates = $xml->xpath('//s:url[s:loc="'.url('/pl').'"]/xhtml:link');
    $hreflangs = [];
    foreach ($homeAlternates as $link) {
        $hreflangs[(string) $link['hreflang']] = (string) $link['href'];
    }

    expect($hreflangs)->toBe([
        'en' => url('/'),
        'pl' => url('/pl'),
        'de' => url('/de'),
        'x-default' => url('/'),
    ]);
});

test('the sitemap contains published translations with alternates and lastmod but never drafts', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'privacy-policy']);
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['slug' => 'polityka-prywatnosci']);
    PageTranslation::factory()->for($page)->locale('de')->draft()->create(['slug' => 'datenschutz']);
    PageTranslation::factory()->draft()->create(['slug' => 'secret-draft']);

    $response = $this->get('/sitemap.xml')->assertOk();
    $content = $response->getContent();

    expect($content)
        ->not->toContain('datenschutz')
        ->not->toContain('secret-draft');

    $xml = simplexml_load_string($content);
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->registerXPathNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

    $entry = $xml->xpath('//s:url[s:loc="'.url('/pl/polityka-prywatnosci').'"]');
    expect($entry)->toHaveCount(1);
    expect((string) $entry[0]->lastmod)->not->toBe('');

    $hreflangs = [];
    foreach ($entry[0]->children('http://www.w3.org/1999/xhtml')->link as $link) {
        $hreflangs[(string) $link->attributes()['hreflang']] = (string) $link->attributes()['href'];
    }

    expect($hreflangs)->toBe([
        'en' => url('/privacy-policy'),
        'pl' => url('/pl/polityka-prywatnosci'),
        'x-default' => url('/privacy-policy'),
    ]);
});

test('a page published only in a non-default locale has no x-default alternate', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['slug' => 'o-nas']);

    $content = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($content)
        ->toContain('<loc>'.url('/pl/o-nas').'</loc>')
        ->not->toContain('hreflang="x-default" href="'.url('/pl/o-nas').'"');
});

test('the sitemap and robots routes are not shadowed by the content catch-all', function () {
    PageTranslation::factory()->published()->create(['slug' => 'sitemap']);
    PageTranslation::factory()->published()->create(['slug' => 'robots']);

    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $this->get('/sitemap')->assertOk()->assertInertia(fn ($inertia) => $inertia->component('pages/show', false));
});
