<?php

declare(strict_types=1);

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Repositories\Settings\SiteSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('renders markdown llms.txt with published pages, articles and active faqs', function (): void {
    SiteSetting::factory()->create([
        'id' => SiteSetting::SINGLETON_ID,
        'site_name' => 'Acme Studio',
        'coming_soon' => false,
        'noindex' => false,
    ]);
    Cache::forget(SiteSettingsRepository::CACHE_KEY);

    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->create([
        'locale' => 'pl',
        'title' => 'Oferta B2B',
        'slug' => 'oferta-b2b',
        'meta_description' => 'Kompleksowe usługi dla firm.',
        'status' => PublicationStatus::Published,
        'published_at' => now()->subDay(),
    ]);
    PageTranslation::factory()->for($page)->create([
        'locale' => 'en',
        'title' => 'Draft Secret',
        'slug' => 'draft-secret',
        'status' => PublicationStatus::Draft,
    ]);

    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->create([
        'locale' => 'pl',
        'title' => 'Nowy wpis na blogu',
        'slug' => 'nowy-wpis',
        'excerpt' => 'Krótkie podsumowanie.',
        'status' => PublicationStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    Faq::factory()->create([
        'locale' => 'pl',
        'question' => 'Ile trwa wdrożenie?',
        'answer' => 'Zwykle jeden tydzień.',
        'published' => true,
    ]);

    $response = $this->get('/llms.txt');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
    $content = (string) $response->getContent();
    expect($content)
        ->toContain('# Acme Studio')
        ->toContain('Oferta B2B')
        ->toContain('Nowy wpis na blogu')
        ->toContain('Ile trwa wdrożenie?')
        ->not->toContain('Draft Secret');
});

it('hides site content in llms.txt when noindex or coming soon is enabled', function (): void {
    SiteSetting::factory()->create([
        'id' => SiteSetting::SINGLETON_ID,
        'site_name' => 'Private Site',
        'noindex' => true,
    ]);
    Cache::forget(SiteSettingsRepository::CACHE_KEY);

    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->create([
        'locale' => 'pl',
        'title' => 'Ukryta strona',
        'slug' => 'ukryta-strona',
        'status' => PublicationStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/llms.txt');

    $response->assertOk();
    expect((string) $response->getContent())
        ->toContain('Site indexing is currently disabled.')
        ->not->toContain('Ukryta strona');
});
