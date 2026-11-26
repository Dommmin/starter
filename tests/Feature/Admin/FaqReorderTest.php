<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\User;
use App\Repositories\Home\HomeSectionRepository;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * Ids of the published public list of the locale, in display order.
 *
 * @return list<int>
 */
function publicFaqIds(string $locale): array
{
    return app(HomeSectionRepository::class)->publishedFaqs($locale, null)->pluck('id')->all();
}

test('editors store a new shared order and every public list follows it', function () {
    $editor = User::factory()->editor()->create();
    $en = Faq::factory()->create(['locale' => 'en', 'position' => 1, 'published' => true]);
    $all = Faq::factory()->create(['locale' => null, 'position' => 2, 'published' => true]);
    $pl = Faq::factory()->create(['locale' => 'pl', 'position' => 3, 'published' => true]);
    $order = [$pl->id, $all->id, $en->id];

    $this->actingAs($editor)
        ->get(route('admin.faqs.order'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/faqs/order', false)
            ->where('items.0.id', $en->id)
            ->where('items.1.locale', null)
            ->where('canReorder', true)
        );

    $this->actingAs($editor)
        ->put(route('admin.faqs.reorder'), ['ids' => $order])
        ->assertRedirect(route('admin.faqs.order'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.faqs.reordered')]);

    expect(Faq::query()->orderBy('position')->pluck('id')->all())->toBe($order)
        ->and(Faq::query()->orderBy('position')->pluck('position')->all())->toBe([1, 2, 3])
        ->and(publicFaqIds('pl'))->toBe([$pl->id, $all->id])
        ->and(publicFaqIds('en'))->toBe([$all->id, $en->id]);

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::FaqsReordered)
        ->and($entry->actor_id)->toBe($editor->id)
        ->and($entry->subject_id)->toBe($pl->id)
        ->and($entry->changes)->toEqual(['order' => ['old' => [$en->id, $all->id, $pl->id], 'new' => $order]]);
});

test('a stale list of ids is a conflict and nothing changes', function () {
    $editor = User::factory()->editor()->create();
    $first = Faq::factory()->create(['position' => 1]);
    $second = Faq::factory()->create(['position' => 2]);
    Faq::factory()->create(['position' => 3]);

    $this->actingAs($editor)
        ->from(route('admin.faqs.order'))
        ->put(route('admin.faqs.reorder'), ['ids' => [$second->id, $first->id]])
        ->assertRedirect(route('admin.faqs.order'))
        ->assertSessionHasErrors(['conflict' => __('admin.faqs.orderConflict')]);

    expect(Faq::query()->orderBy('id')->pluck('position')->all())->toBe([1, 2, 3])
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a viewer cannot reorder faqs and nothing changes', function () {
    $first = Faq::factory()->create(['position' => 1]);
    $second = Faq::factory()->create(['position' => 2]);

    $this->actingAs(User::factory()->viewer()->create())
        ->put(route('admin.faqs.reorder'), ['ids' => [$second->id, $first->id]])
        ->assertForbidden();

    expect(Faq::query()->orderBy('id')->pluck('position')->all())->toBe([1, 2])
        ->and(AuditLog::query()->count())->toBe(0);
});

test('clearing the position moves the faq to the end of the shared order', function () {
    $faq = Faq::factory()->create(['position' => 1]);
    Faq::factory()->create(['position' => 6]);

    $this->actingAs(User::factory()->editor()->create())
        ->put(route('admin.faqs.update', $faq), [
            'question' => $faq->question,
            'answer' => $faq->answer,
            'position' => '',
            'published' => true,
            'updated_at' => $faq->updated_at?->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect($faq->fresh()?->position)->toBe(7);
});

test('the migration numbers all faqs in the current public order and is idempotent', function () {
    $rows = [
        ['locale' => 'pl', 'position' => 10],
        ['locale' => null, 'position' => 5],
        ['locale' => 'en', 'position' => null],
        ['locale' => 'pl', 'position' => 10],
        ['locale' => null, 'position' => null],
        ['locale' => 'en', 'position' => -2],
    ];
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = Faq::factory()->create([...$row, 'published' => true])->id;
    }
    $before = ['pl' => publicFaqIds('pl'), 'en' => publicFaqIds('en'), 'de' => publicFaqIds('de')];

    $migration = require database_path('migrations/2026_11_26_195757_normalize_faq_positions.php');
    $migration->up();

    $positions = Faq::query()->pluck('position', 'id')->all();
    expect($positions)->toEqual([
        $ids[5] => 1,
        $ids[1] => 2,
        $ids[0] => 3,
        $ids[3] => 4,
        $ids[2] => 5,
        $ids[4] => 6,
    ])
        ->and(['pl' => publicFaqIds('pl'), 'en' => publicFaqIds('en'), 'de' => publicFaqIds('de')])->toBe($before);

    DB::enableQueryLog();
    $migration->up();
    $updates = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update'));

    expect($updates)->toBe([])
        ->and(Faq::query()->pluck('position', 'id')->all())->toEqual($positions);
});
