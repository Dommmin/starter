<?php

use App\Models\Redirect;

test('an active redirect answers an unrouted old address with its status and counts the hit', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/oferta/strony-www.html', 'target' => '/offer']);

    $this->get('/Oferta/Strony-WWW.html/')->assertStatus(301)->assertRedirect('http://localhost/offer');

    expect($redirect->refresh()->hits)->toBe(1)
        ->and($redirect->last_hit_at)->not->toBeNull();
});

test('a temporary redirect uses 302 and an external target is kept', function () {
    Redirect::factory()->temporary()->create(['source_path' => '/promo', 'target' => 'https://partner.example.com/sale']);

    $this->get('/promo')->assertStatus(302)->assertRedirect('https://partner.example.com/sale');
});

test('the query string is passed on unless the target has its own', function () {
    Redirect::factory()->create(['source_path' => '/a', 'target' => '/new-a#top']);
    Redirect::factory()->create(['source_path' => '/b', 'target' => '/new-b?ref=old']);

    $this->get('/a?utm_source=mail')->assertRedirect('http://localhost/new-a?utm_source=mail#top');
    $this->get('/b?utm_source=mail')->assertRedirect('http://localhost/new-b?ref=old');
});

test('an inactive redirect is ignored', function () {
    $redirect = Redirect::factory()->inactive()->create(['source_path' => '/old.html', 'target' => '/new']);

    $this->get('/old.html')->assertNotFound();

    expect($redirect->refresh()->hits)->toBe(0);
});

test('non-GET requests are not redirected', function () {
    Redirect::factory()->create(['source_path' => '/old-form', 'target' => '/new']);

    $this->post('/old-form')->assertStatus(405);
});

test('panel paths are never redirected even if stored', function () {
    Redirect::factory()->create(['source_path' => '/admin', 'target' => '/new']);

    $this->get('/admin')->assertRedirect(route('login'));
});

test('saving, switching off and deleting refresh the cached redirect map', function () {
    $this->get('/cached.html')->assertNotFound();

    $redirect = Redirect::factory()->create(['source_path' => '/cached.html', 'target' => '/first']);
    $this->get('/cached.html')->assertRedirect('http://localhost/first');

    $redirect->update(['target' => '/second']);
    $this->get('/cached.html')->assertRedirect('http://localhost/second');

    $redirect->update(['is_active' => false]);
    $this->get('/cached.html')->assertNotFound();

    $redirect->update(['is_active' => true]);
    $redirect->delete();
    $this->get('/cached.html')->assertNotFound();
});

test('counting a hit does not change the version used by the editor', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/counted', 'target' => '/new']);
    $version = $redirect->updated_at?->toIso8601String();

    $this->travel(5)->minutes();
    $this->get('/counted')->assertRedirect();

    expect($redirect->refresh()->updated_at?->toIso8601String())->toBe($version);
});
