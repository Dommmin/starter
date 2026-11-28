<?php

use App\Actions\Admin\SearchAdminRecords;
use App\Actions\Home\BuildHomeSections;
use App\Actions\Home\EnsureHomeSections;
use App\Actions\Seo\BuildLlmsTxt;
use App\Enums\HomeSectionType;
use App\Models\Faq;
use App\Models\Page;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false, 'modules.faq' => false]);
});

test('every route of the disabled FAQ module carries the module gate', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'admin.faqs.')
        || $route->getName() === 'admin.assistant.faqs');

    expect($routes->count())->toBeGreaterThan(5)
        ->and($routes->reject(fn (RoutingRoute $route): bool => in_array('module:faq', $route->gatherMiddleware(), true))->map->getName()->values()->all())->toBe([]);
});

test('an administrator cannot reach or change a disabled module', function () {
    $admin = User::factory()->admin()->create();
    $faq = Faq::factory()->create(['question' => 'Kept question?']);

    $this->actingAs($admin)->get(route('admin.faqs.index'))->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.faqs.destroy', $faq))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.import.show', 'faqs'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.index'))
        ->assertInertia(fn (Assert $assert) => $assert->where('disabledModules', ['faq']));

    expect($admin->can('viewAny', Faq::class))->toBeFalse()
        ->and(Faq::query()->count())->toBe(1);
});

test('a disabled module disappears from search, llms.txt and page sections', function () {
    $editor = User::factory()->editor()->create();
    Faq::factory()->create(['question' => 'Hidden question?', 'published' => true]);
    $page = Page::factory()->create();
    $page->sections()->create(['locale' => 'en', 'type' => HomeSectionType::Faq, 'position' => 1, 'enabled' => true, 'content' => EnsureHomeSections::placeholder(HomeSectionType::Faq, 'en')]);

    $results = app(SearchAdminRecords::class)->handle($editor, 'Hidden question');

    expect(json_encode($results))->not->toContain('Hidden question')
        ->and(app(BuildLlmsTxt::class)->handle())->not->toContain('Hidden question')
        ->and(app(BuildHomeSections::class)->buildFromCollection($page->sections()->get(), 'en'))->toBe([]);

    $this->actingAs($editor)->post(route('admin.pages.sections.store', $page), ['locale' => 'en', 'type' => 'faq'])
        ->assertSessionHasErrors('type');
    $this->actingAs($editor)->get(route('admin.pages.sections.index', $page))
        ->assertInertia(fn (Assert $assert) => $assert->where('availableTypes', fn ($types): bool => ! collect($types)->contains('faq')));
});

test('enabled modules share nothing extra and keep their pages', function () {
    config(['modules.faq' => true]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.faqs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert) => $assert->missing('disabledModules'));
});
