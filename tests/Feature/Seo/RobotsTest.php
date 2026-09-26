<?php

test('production allows crawling except private areas and points to the sitemap', function () {
    $this->app['env'] = 'production';

    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeaderMissing('Set-Cookie');

    expect($response->getContent())
        ->toContain("User-agent: *\nAllow: /\n")
        ->toContain("Disallow: /admin\n")
        ->toContain("Disallow: /settings\n")
        ->toContain('Sitemap: '.url('/sitemap.xml'))
        ->not->toContain("Disallow: /\n");
});

test('non-production environments disallow all crawling', function (string $environment) {
    $this->app['env'] = $environment;

    expect($this->get('/robots.txt')->assertOk()->getContent())
        ->toBe("User-agent: *\nDisallow: /\n");
})->with(['local', 'staging', 'testing']);
