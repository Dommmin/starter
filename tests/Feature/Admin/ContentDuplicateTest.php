<?php

declare(strict_types=1);

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('duplicates a page with translations as drafts and unique slugs', function (): void {
    $editor = User::factory()->editor()->create();

    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->create([
        'locale' => 'pl',
        'title' => 'Usługi',
        'slug' => 'uslugi',
        'status' => PublicationStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs($editor)
        ->post(route('admin.pages.duplicate', $page))
        ->assertRedirect();

    $copy = Page::query()->where('id', '!=', $page->id)->firstOrFail();
    $translation = $copy->translations()->where('locale', 'pl')->firstOrFail();

    expect($translation->title)->toBe('Usługi (Copy)')
        ->and($translation->slug)->toBe('uslugi-copy')
        ->and($translation->status)->toBe(PublicationStatus::Draft)
        ->and($translation->published_at)->toBeNull();

    $this->actingAs($editor)
        ->post(route('admin.pages.duplicate', $page))
        ->assertRedirect();

    $secondCopy = Page::query()->whereNotIn('id', [$page->id, $copy->id])->firstOrFail();
    expect($secondCopy->translations()->where('locale', 'pl')->firstOrFail()->slug)
        ->toBe('uslugi-copy-2');
});

it('duplicates an article with tags and translations as drafts', function (): void {
    $editor = User::factory()->editor()->create();
    $tag = Tag::factory()->create();

    $article = Article::factory()->create();
    $article->tags()->attach($tag);
    ArticleTranslation::factory()->for($article)->create([
        'locale' => 'pl',
        'title' => 'Case study',
        'slug' => 'case-study',
        'status' => PublicationStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs($editor)
        ->post(route('admin.articles.duplicate', $article))
        ->assertRedirect();

    $copy = Article::query()->where('id', '!=', $article->id)->firstOrFail();
    $translation = $copy->translations()->where('locale', 'pl')->firstOrFail();

    expect($translation->title)->toBe('Case study (Copy)')
        ->and($translation->slug)->toBe('case-study-copy')
        ->and($translation->status)->toBe(PublicationStatus::Draft)
        ->and($copy->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});

it('forbids viewer from duplicating pages or articles', function (): void {
    $viewer = User::factory()->viewer()->create();
    $page = Page::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs($viewer)
        ->post(route('admin.pages.duplicate', $page))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('admin.articles.duplicate', $article))
        ->assertForbidden();

    expect(Page::query()->count())->toBe(1)
        ->and(Article::query()->count())->toBe(1);
});
