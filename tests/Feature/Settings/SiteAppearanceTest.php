<?php

use App\Models\MediaAsset;
use App\Models\SiteSetting;

test('the first HTML carries the accent, typography and default theme of the settings', function () {
    SiteSetting::factory()->create([
        'accent' => 'violet',
        'typography' => 'classic',
        'default_theme' => 'dark',
        'google_site_verification' => 'token_123',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-accent="violet"')
        ->toContain('data-typography="classic"')
        ->toContain('data-theme-default="dark"')
        ->toContain('class="dark"')
        ->toContain('<meta name="google-site-verification" content="token_123">');
});

test('the visitor theme cookie wins over the default theme', function () {
    SiteSetting::factory()->create(['default_theme' => 'dark']);

    $html = $this->withUnencryptedCookie('appearance', 'light')->get('/')->getContent();

    expect($html)->toContain('data-theme-default="dark"')
        ->not->toContain('class="dark"');
});

test('without saved presets APP_ACCENT and the default typography apply', function () {
    config(['app.accent' => 'green']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('data-accent="green"')
        ->not->toContain('data-typography=')
        ->toContain('data-theme-default="system"');
});

test('a favicon from the media library replaces the default icons', function () {
    $favicon = MediaAsset::factory()->withVariants()->create();
    SiteSetting::factory()->create(['favicon_media_id' => $favicon->id]);

    $html = $this->get('/')->getContent();

    expect($html)->toMatch('/<link rel="icon" href="[^"]+" type="image\/(png|jpeg)">/')
        ->not->toContain('href="/favicon.ico"');
});

test('a favicon that is not clean falls back to the default icons', function () {
    $favicon = MediaAsset::factory()->create();
    SiteSetting::factory()->create(['favicon_media_id' => $favicon->id]);

    expect($this->get('/')->getContent())->toContain('href="/favicon.ico"');
});
