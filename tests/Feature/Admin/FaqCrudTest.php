<?php

use App\Models\Faq;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * A valid store/update payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function faqPayload(array $overrides = []): array
{
    return [
        'question' => 'Example question',
        'answer' => 'Example answer',
        'position' => 7,
        'published' => true,
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('admin.faqs.index'))->assertRedirect(route('login'));
});

test('the list exposes the typed list payload with defaults', function () {
    $editor = User::factory()->editor()->create();
    $faq = Faq::factory()->create();

    $this->actingAs($editor)->get(route('admin.faqs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/index', false)
            ->has('items', 1)
            ->where('items.0.id', $faq->id)
            ->where('pagination', ['page' => 1, 'totalPages' => 1, 'total' => 1, 'perPage' => 15])
            ->where('filters', [
                'search' => '',
                'sort' => 'created_at',
                'direction' => 'desc',
                'published' => 'all',
            ])
            ->where('can', ['create' => true, 'delete' => false])
        );
});

test('search matches the searchable columns', function () {
    $admin = User::factory()->admin()->create();
    $match = Faq::factory()->create(['question' => 'Needle in a haystack']);
    Faq::factory()->create(['question' => 'Something else']);

    $this->actingAs($admin)->get(route('admin.faqs.index', ['search' => 'needle']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/index', false)
            ->has('items', 1)
            ->where('items.0.id', $match->id)
            ->where('filters.search', 'needle')
        );
});

test('sorting accepts only allowlisted columns', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.faqs.index', ['sort' => 'question', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/index', false)
            ->where('filters.sort', 'question')
            ->where('filters.direction', 'asc')
        );

    $this->actingAs($admin)
        ->from(route('admin.faqs.index'))
        ->get(route('admin.faqs.index', ['sort' => 'not_a_column', 'direction' => 'sideways']))
        ->assertRedirect(route('admin.faqs.index'))
        ->assertSessionHasErrors(['sort', 'direction']);
});

test('the published filter narrows the list and rejects unknown values', function () {
    $admin = User::factory()->admin()->create();
    $match = Faq::factory()->create(['published' => true]);
    Faq::factory()->create(['published' => false]);

    $this->actingAs($admin)->get(route('admin.faqs.index', ['published' => 'yes']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/index', false)
            ->has('items', 1)
            ->where('items.0.id', $match->id)
            ->where('filters.published', 'yes')
        );

    $this->actingAs($admin)
        ->from(route('admin.faqs.index'))
        ->get(route('admin.faqs.index', ['published' => 'not_a_value']))
        ->assertSessionHasErrors(['published']);
});

test('the create screen receives a blank form', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.faqs.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/create', false)
            ->where('faq.id', null)
            ->where('faq.updatedAt', null)
            ->where('can.create', true)
        );
});

test('an editor creates a faq', function () {
    $editor = User::factory()->editor()->create();

    $response = $this->actingAs($editor)->post(route('admin.faqs.store'), faqPayload());

    $faq = Faq::query()->sole();
    $response->assertRedirect(route('admin.faqs.edit', $faq))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.faqs.created')]);

    expect($faq?->question)->toBe('Example question')
        ->and($faq?->answer)->toBe('Example answer')
        ->and($faq?->position)->toBe(7)
        ->and($faq?->published)->toBeTrue();
});

test('store validates required fields and creates nothing', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.faqs.store'), [])
        ->assertSessionHasErrors(['question', 'answer', 'published']);

    $this->actingAs($editor)->post(route('admin.faqs.store'), faqPayload(['question' => str_repeat('a', 256)]))
        ->assertSessionHasErrors(['question']);

    expect(Faq::query()->count())->toBe(0);
});

test('the edit screen exposes the stored values and the version for locking', function () {
    $admin = User::factory()->admin()->create();
    $faq = Faq::factory()->create();

    $this->actingAs($admin)->get(route('admin.faqs.edit', $faq))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/edit', false)
            ->where('faq.id', $faq->id)
            ->where('faq.updatedAt', $faq->updated_at?->toIso8601String())
            ->where('can.delete', true)
        );
});

test('an update with the current version saves the values', function () {
    $admin = User::factory()->admin()->create();
    $faq = Faq::factory()->create();

    $this->travel(5)->minutes();

    $this->actingAs($admin)->put(route('admin.faqs.update', $faq), faqPayload([
        'updated_at' => $faq->updated_at?->toIso8601String(),
    ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.faqs.edit', $faq))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.faqs.updated')]);

    $original = $faq;
    $faq = $faq->fresh();
    expect($faq?->updated_at?->greaterThan($original->updated_at))->toBeTrue();

    expect($faq?->question)->toBe('Example question')
        ->and($faq?->answer)->toBe('Example answer')
        ->and($faq?->position)->toBe(7)
        ->and($faq?->published)->toBeTrue();
});

test('a stale version is rejected with a conflict error and nothing changes', function () {
    $admin = User::factory()->admin()->create();
    $faq = Faq::factory()->create();
    $original = $faq->fresh()?->toArray();

    $this->actingAs($admin)
        ->from(route('admin.faqs.edit', $faq))
        ->put(route('admin.faqs.update', $faq), faqPayload([
            'updated_at' => $faq->updated_at?->subMinute()->toIso8601String(),
        ]))
        ->assertRedirect(route('admin.faqs.edit', $faq))
        ->assertSessionHasErrors(['conflict' => __('admin.faqs.conflict')]);

    expect($faq->fresh()?->toArray())->toBe($original);
});

test('users without a panel role can neither open nor mutate faqs', function () {
    $user = User::factory()->create();
    $faq = Faq::factory()->create();
    $original = $faq->fresh()?->toArray();

    $this->actingAs($user)->get(route('admin.faqs.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.faqs.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.faqs.edit', $faq))->assertForbidden();
    $this->actingAs($user)->post(route('admin.faqs.store'), faqPayload())->assertForbidden();
    $this->actingAs($user)->put(route('admin.faqs.update', $faq), faqPayload([
        'updated_at' => $faq->updated_at?->toIso8601String(),
    ]))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.faqs.destroy', $faq))->assertForbidden();

    expect(Faq::query()->count())->toBe(1)
        ->and($faq->fresh()?->toArray())->toBe($original);
});

test('an editor cannot delete a faq', function () {
    $editor = User::factory()->editor()->create();
    $faq = Faq::factory()->create();

    $this->actingAs($editor)->delete(route('admin.faqs.destroy', $faq))->assertForbidden();

    expect(Faq::query()->whereKey($faq->id)->exists())->toBeTrue();
});

test('an admin deletes a faq', function () {
    $admin = User::factory()->admin()->create();
    $faq = Faq::factory()->create();

    $this->actingAs($admin)->delete(route('admin.faqs.destroy', $faq))
        ->assertRedirect(route('admin.faqs.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.faqs.deleted')]);

    expect(Faq::query()->count())->toBe(0);
});
