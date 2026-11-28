<?php

use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use App\Models\Redirect;
use App\Models\User;
use App\Services\Content\ContentQualityReport;
use App\Services\Content\InternalLinkChecker;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false, 'app.url' => 'https://site.test']);
});

function qualityPage(string $slug, array $attributes = [], string $state = 'published'): PageTranslation
{
    return PageTranslation::factory()->for(Page::factory())->locale('en')->{$state}()->create(['slug' => $slug, 'title' => $slug, ...$attributes]);
}

test('only internal addresses visitors cannot open are broken', function (string $href, bool $broken) {
    $target = qualityPage('live');
    qualityPage('hidden', state: 'draft');
    PageSlugRedirect::query()->create(['locale' => 'en', 'old_slug' => 'former', 'page_translation_id' => $target->id]);
    Redirect::factory()->create(['source_path' => '/old-offer', 'target' => '/live', 'is_active' => true]);

    expect(app(InternalLinkChecker::class)->isBroken($href))->toBe($broken);
})->with([
    'published page' => ['/live', false],
    'published page with query and anchor' => ['/live?a=1#b', false],
    'default locale alias' => ['/en/live', false],
    'former slug' => ['/former', false],
    'managed redirect' => ['/old-offer', false],
    'home' => ['/', false],
    'external' => ['https://example.com/nothing', false],
    'mail' => ['mailto:a@example.com', false],
    'anchor' => ['#top', false],
    'draft page' => ['/hidden', true],
    'unknown page' => ['/nowhere', true],
    'unknown deep path' => ['/a/b/c', true],
    'own host' => ['https://site.test/nowhere', true],
    'missing article' => ['/articles/nowhere', true],
]);

test('the report lists problems of published content and images', function () {
    qualityPage('complete', ['meta_description' => 'Fine', 'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Go', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/nowhere']]]],
    ]]]]]);
    qualityPage('bare', ['meta_description' => null, 'body' => null]);
    qualityPage('ignored-draft', ['meta_description' => null, 'body' => null], 'draft');
    MediaAsset::factory()->clean()->create(['alt' => null]);

    $report = app(ContentQualityReport::class)->build();
    $found = array_map(fn ($issue): string => $issue->type->value.' '.$issue->label, $report->issues);

    expect($found)->toContain('broken_link complete', 'empty_content bare', 'missing_description bare')
        ->and(implode(' ', $found))->not->toContain('ignored-draft')
        ->and(array_filter($found, fn (string $line): bool => str_starts_with($line, 'missing_alt_text')))->toHaveCount(1)
        ->and($found[0])->toBe('broken_link complete');
});

test('every panel user can open the report and others cannot', function () {
    $this->actingAs(User::factory()->viewer()->create())->get(route('admin.quality.index'))
        ->assertInertia(fn (Assert $assert) => $assert->component('admin/quality/index')->where('total', 0));

    $this->actingAs(User::factory()->create())->get(route('admin.quality.index'))->assertForbidden();
});
