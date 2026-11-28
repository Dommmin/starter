<?php

use App\Enums\HomeSectionType;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can switch page to sections mode, manage page sections, and render them publicly', function () {
    $admin = User::factory()->admin()->create();
    $locale = config('app.fallback_locale', 'pl');

    $page = Page::factory()->create(['layout_mode' => 'sections']);
    $page->translations()->create([
        'locale' => $locale,
        'title' => 'Landing Offer',
        'slug' => 'landing-offer',
        'status' => PublicationStatus::Published,
        'published_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.pages.sections.index', $page))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('admin/pages/sections/index')
            ->where('pageId', $page->id));

    $this->actingAs($admin)
        ->post(route('admin.pages.sections.store', $page), [
            'locale' => $locale,
            'type' => HomeSectionType::Hero->value,
        ])
        ->assertRedirect();

    $hero = PageSection::query()->where('page_id', $page->id)->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.pages.sections.update', [$page, $hero]), [
            'updated_at' => $hero->updated_at->toIso8601String(),
            'content' => [
                'eyebrow' => 'New Offer',
                'title' => 'Hero Landing Title',
                'description' => 'Hero description',
                'primaryAction' => null,
                'secondaryAction' => null,
                'imageId' => null,
                'stats' => [],
            ],
        ])
        ->assertRedirect(route('admin.pages.sections.edit', [$page, $hero]));

    $this->actingAs($admin)
        ->post(route('admin.pages.sections.store', $page), [
            'locale' => $locale,
            'type' => HomeSectionType::Cta->value,
        ])
        ->assertRedirect();

    $cta = PageSection::query()
        ->where('page_id', $page->id)
        ->where('id', '!=', $hero->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.pages.sections.reorder', $page), [
            'locale' => $locale,
            'order' => [$cta->id, $hero->id],
        ])
        ->assertRedirect();

    $this->get(route('pages.show', ['slug' => 'landing-offer']))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('pages/show')
            ->where('layoutMode', 'sections')
            ->has('sections', 2)
            ->where('sections.0.type', 'cta')
            ->where('sections.1.type', 'hero'));

    $this->actingAs($admin)
        ->patch(route('admin.pages.sections.visibility', [$page, $cta]), [
            'enabled' => false,
        ])
        ->assertRedirect();

    $this->get(route('pages.show', ['slug' => 'landing-offer']))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('sections', 1)
            ->where('sections.0.type', 'hero'));

    $this->actingAs($admin)
        ->delete(route('admin.pages.sections.destroy', [$page, $cta]))
        ->assertRedirect();

    expect(PageSection::query()->where('page_id', $page->id)->count())->toBe(1);
});
