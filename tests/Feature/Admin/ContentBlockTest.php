<?php

use App\Actions\Home\EnsureHomeSections;
use App\Enums\HomeSectionType;
use App\Models\ContentBlock;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Models\User;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @return array{Page, PageSection}
 */
function pageWithCta(): array
{
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->locale('en')->create();
    $section = $page->sections()->create([
        'locale' => 'en', 'type' => HomeSectionType::Cta, 'position' => 1, 'enabled' => true,
        'content' => EnsureHomeSections::placeholder(HomeSectionType::Cta, 'en'),
    ]);

    return [$page, $section];
}

test('editing a shared section changes every page that uses the block and keeps their history', function () {
    $editor = User::factory()->editor()->create();
    [$first, $section] = pageWithCta();
    [$second] = pageWithCta();

    $this->actingAs($editor)->post(route('admin.pages.sections.share', [$first, $section]), ['name' => 'Main CTA']);
    $block = ContentBlock::query()->sole();
    $this->actingAs($editor)->post(route('admin.pages.blocks.insert', $second), ['content_block_id' => $block->id]);
    ContentRevision::query()->delete();

    $content = [...$section->refresh()->content->toArray(), 'title' => 'New shared title'];
    $this->actingAs($editor)->put(route('admin.pages.sections.update', [$first, $section]), [
        'updated_at' => $section->updated_at?->toIso8601String(),
        'content' => $content,
    ])->assertSessionHasNoErrors();

    $linked = PageSection::query()->where('content_block_id', $block->id)->get();
    expect($linked)->toHaveCount(2)
        ->and($linked->map(fn (PageSection $item): mixed => $item->content->toArray()['title'])->unique()->all())->toBe(['New shared title'])
        ->and($block->refresh()->content->toArray()['title'])->toBe('New shared title')
        ->and(ContentRevision::query()->pluck('subject_id')->sort()->values()->all())->toBe([$first->id, $second->id]);
});

test('a detached section stops following the block', function () {
    $editor = User::factory()->editor()->create();
    [$page, $section] = pageWithCta();
    $this->actingAs($editor)->post(route('admin.pages.sections.share', [$page, $section]), ['name' => 'CTA']);

    $this->actingAs($editor)->post(route('admin.pages.sections.detach', [$page, $section]));

    expect($section->refresh()->content_block_id)->toBeNull();
});

test('a block in use cannot be deleted', function () {
    $editor = User::factory()->editor()->create();
    [$page, $section] = pageWithCta();
    $this->actingAs($editor)->post(route('admin.pages.sections.share', [$page, $section]), ['name' => 'CTA']);
    $block = ContentBlock::query()->sole();

    $this->actingAs($editor)->delete(route('admin.pages.blocks.destroy', [$page, $block]))->assertSessionHasErrors('block');
    expect(ContentBlock::query()->count())->toBe(1);

    $section->refresh()->forceFill(['content_block_id' => null])->save();
    $this->actingAs($editor)->delete(route('admin.pages.blocks.destroy', [$page, $block]))->assertSessionHasNoErrors();
    expect(ContentBlock::query()->count())->toBe(0);
});

test('restoring a page links a section again only while it still equals the block', function () {
    $editor = User::factory()->editor()->create();
    [$page, $section] = pageWithCta();
    $this->actingAs($editor)->post(route('admin.pages.sections.share', [$page, $section]), ['name' => 'CTA']);
    $block = ContentBlock::query()->sole();
    $this->actingAs($editor)->delete(route('admin.pages.sections.destroy', [$page, $section]));
    $withLink = ContentRevision::query()->latest('id')->firstOrFail();

    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, $withLink->id]));
    expect($page->sections()->sole()->content_block_id)->toBe($block->id);

    $block->update(['content' => HomeSectionType::Cta->contentClass()::from([...$block->content->toArray(), 'title' => 'Changed later'])]);
    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, $withLink->id]));

    expect($page->sections()->sole()->content_block_id)->toBeNull();
});

test('a viewer cannot share, insert or delete blocks', function () {
    $viewer = User::factory()->viewer()->create();
    [$page, $section] = pageWithCta();
    $block = ContentBlock::factory()->create();

    $this->actingAs($viewer)->post(route('admin.pages.sections.share', [$page, $section]), ['name' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.pages.blocks.insert', $page), ['content_block_id' => $block->id])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.pages.blocks.destroy', [$page, $block]))->assertForbidden();

    expect(ContentBlock::query()->count())->toBe(1)->and($page->sections()->count())->toBe(1)
        ->and($section->refresh()->content_block_id)->toBeNull();
});
