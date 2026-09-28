<?php

use App\Actions\Home\EnsureHomeSections;
use App\Data\Home\HeroContentData;
use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use Database\Seeders\HomeSectionSeeder;
use Illuminate\Database\QueryException;

test('every public locale gets one section of every type and a second run creates nothing', function () {
    $ensure = app(EnsureHomeSections::class);

    expect($ensure->handle())->toBe(3 * count(HomeSectionType::cases()));

    $hero = HomeSection::query()->where('locale', 'pl')->where('type', HomeSectionType::Hero)->sole();
    $hero->content = new HeroContentData(title: 'Edited');
    $hero->enabled = true;
    $hero->save();

    expect($ensure->handle())->toBe(0)
        ->and(HomeSection::query()->count())->toBe(21)
        ->and($hero->fresh()?->content->title)->toBe('Edited')
        ->and($hero->fresh()?->enabled)->toBeTrue()
        ->and(HomeSection::query()->where('locale', 'en')->orderBy('position')->pluck('type')->all())
        ->toBe(HomeSectionType::cases());
});

test('the database rejects a second section of the same type in a locale', function () {
    HomeSection::factory()->ofType(HomeSectionType::Faq)->create(['locale' => 'pl']);
    HomeSection::factory()->ofType(HomeSectionType::Faq)->create(['locale' => 'de']);

    expect(fn () => HomeSection::factory()->ofType(HomeSectionType::Faq)->create(['locale' => 'pl']))
        ->toThrow(QueryException::class);
});

test('the content cast accepts only the data class of the section type', function () {
    $section = HomeSection::factory()->ofType(HomeSectionType::Cta)->create();

    expect(fn () => $section->content = new HeroContentData(title: 'Wrong'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HomeSection::query()->create(['locale' => 'en', 'type' => 'banner', 'position' => 1, 'content' => new HeroContentData(title: 'X')]))
        ->toThrow(ValueError::class);
});

test('the demo seeder rebuilds the former landing page and is idempotent', function () {
    $this->seed(HomeSectionSeeder::class);
    $this->seed(HomeSectionSeeder::class);

    expect(HomeSection::query()->count())->toBe(21);

    $enabled = HomeSection::query()->where('locale', 'en')->where('enabled', true)->orderBy('position')->pluck('type')->all();
    expect($enabled)->toBe([HomeSectionType::Hero, HomeSectionType::Features, HomeSectionType::Contact, HomeSectionType::Cta]);

    $plHero = HomeSection::query()->where('locale', 'pl')->where('type', HomeSectionType::Hero)->sole();
    expect($plHero->content->title)->toBe(__('public.landing.heroTitle', [], 'pl'));
});
