<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('admins and editors copy a faq as a new unpublished faq placed last', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $source = Faq::factory()->create([
        'question' => 'Source question',
        'answer' => 'Source answer',
        'locale' => 'en',
        'position' => 3,
        'published' => true,
    ]);
    Faq::factory()->create(['position' => 9]);
    $sourceBefore = $source->fresh()?->getAttributes();

    $response = $this->actingAs($user)->post(route('admin.faqs.duplicate', $source));

    $copy = Faq::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('admin.faqs.edit', $copy))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.faqs.duplicated')]);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->question)->toBe('Source question')
        ->and($copy->answer)->toBe('Source answer')
        ->and($copy->locale)->toBe('en')
        ->and($copy->published)->toBeFalse()
        ->and($copy->position)->toBe(10)
        ->and(Faq::query()->count())->toBe(3)
        ->and($source->fresh()?->getAttributes())->toBe($sourceBefore);

    $entry = AuditLog::query()->sole();

    expect($entry->action)->toBe(AuditAction::FaqDuplicated)
        ->and($entry->actor_id)->toBe($user->id)
        ->and($entry->subject_type)->toBe($copy->getMorphClass())
        ->and($entry->subject_id)->toBe($copy->id)
        ->and($entry->changes)->toBe(['duplicated_from' => ['old' => null, 'new' => $source->id]]);
})->with(['admin', 'editor']);

test('a copy of the only faq without a position gets the first position', function () {
    $source = Faq::factory()->create(['position' => null]);

    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.faqs.duplicate', $source))
        ->assertRedirect();

    expect(Faq::query()->whereKeyNot($source->id)->sole()->position)->toBe(1);
});

test('a viewer cannot copy a faq and nothing is stored', function () {
    $source = Faq::factory()->create();

    $this->actingAs(User::factory()->viewer()->create())
        ->post(route('admin.faqs.duplicate', $source))
        ->assertForbidden();

    expect(Faq::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the editor offers copying only to roles that may copy', function (string $role, bool $canDuplicate) {
    $faq = Faq::factory()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.faqs.edit', $faq))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/faqs/edit', false)
            ->where('can.duplicate', $canDuplicate)
        );
})->with([
    'editor' => ['editor', true],
    'viewer' => ['viewer', false],
]);
