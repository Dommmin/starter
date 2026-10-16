<?php

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use Database\Seeders\LegalPageSeeder;

test('legal page templates are seeded as drafts in every language outside the local demo', function () {
    $this->seed(LegalPageSeeder::class);

    expect(Page::query()->count())->toBe(3);

    foreach (LegalPageSeeder::SLUGS as $slugs) {
        foreach ($slugs as $locale => $slug) {
            $translation = PageTranslation::query()->where('locale', $locale)->where('slug', $slug)->sole();

            expect($translation->status)->toBe(PublicationStatus::Draft)
                ->and($translation->published_at)->toBeNull()
                ->and(json_encode($translation->body))->toContain('[[');
        }
    }
});

test('seeding legal pages again adds nothing and keeps an existing page', function () {
    $this->seed(LegalPageSeeder::class);
    PageTranslation::query()->where('slug', 'regulamin')->update(['title' => 'Nasz regulamin']);

    $this->seed(LegalPageSeeder::class);

    expect(Page::query()->count())->toBe(3)
        ->and(PageTranslation::query()->where('slug', 'regulamin')->value('title'))->toBe('Nasz regulamin');
});

test('the cookie banner policy slugs point at the seeded legal pages', function () {
    foreach (['pl', 'en', 'de'] as $locale) {
        expect(config("consent.policy_slugs.{$locale}"))->toBe([
            LegalPageSeeder::SLUGS['cookies'][$locale],
            LegalPageSeeder::SLUGS['privacy'][$locale],
        ]);
    }
});
