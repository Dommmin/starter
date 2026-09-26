<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @return array<string, mixed>
 */
function auditTranslationInput(string $title, string $slug, string $status, string $bodyText = 'Confidential body text'): array
{
    return [
        'title' => $title,
        'slug' => $slug,
        'meta_description' => 'Summary',
        'body' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $bodyText]]]],
        ],
        'status' => $status,
    ];
}

test('creating a published page records created and published events by the actor', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => auditTranslationInput('Pricing', 'pricing', 'published')],
    ])->assertRedirect();

    $page = Page::query()->sole();
    $created = AuditLog::query()->where('action', AuditAction::PageCreated)->sole();

    expect($created->actor_id)->toBe($editor->id)
        ->and($created->subject_type)->toBe($page->getMorphClass())
        ->and($created->subject_id)->toBe($page->id)
        ->and($created->changes['en.title'])->toBe(['old' => null, 'new' => 'Pricing'])
        ->and($created->changes['en.body'])->toBe(['redacted' => true])
        ->and(AuditLog::query()->where('action', AuditAction::PagePublished)->sole()->changes)
        ->toBe(['en.status' => ['old' => null, 'new' => 'published']]);
});

test('updating a page records changed fields without the body and status changes separately', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['title' => 'Old', 'slug' => 'old', 'meta_description' => 'Summary']);
    $this->travel(1)->seconds();

    $this->actingAs($admin)->put(route('admin.pages.update', $page), [
        'updated_at' => $page->updated_at->toIso8601String(),
        'translations' => ['en' => auditTranslationInput('New', 'old', 'draft', 'Secret new body')],
    ])->assertSessionHasNoErrors();

    $updated = AuditLog::query()->where('action', AuditAction::PageUpdated)->sole();

    expect(array_keys($updated->changes))->toEqualCanonicalizing(['en.title', 'en.body'])
        ->and($updated->changes['en.title'])->toBe(['old' => 'Old', 'new' => 'New'])
        ->and($updated->changes['en.body'])->toBe(['redacted' => true])
        ->and(json_encode($updated->changes))->not->toContain('Secret new body')
        ->and(AuditLog::query()->where('action', AuditAction::PageUnpublished)->sole()->changes)
        ->toBe(['en.status' => ['old' => 'published', 'new' => 'draft']])
        ->and(AuditLog::query()->where('action', AuditAction::PagePublished)->exists())->toBeFalse();
});

test('publishing a draft records a published event', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->draft()->create(['title' => 'Same', 'slug' => 'same', 'meta_description' => 'Summary']);
    $this->travel(1)->seconds();

    $this->actingAs($admin)->put(route('admin.pages.update', $page), [
        'updated_at' => $page->updated_at->toIso8601String(),
        'translations' => ['en' => [
            'title' => 'Same', 'slug' => 'same', 'meta_description' => 'Summary',
            'body' => $page->translations()->sole()->body, 'status' => 'published',
        ]],
    ])->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('action', AuditAction::PagePublished)->sole()->actor_id)->toBe($admin->id)
        ->and(AuditLog::query()->where('action', AuditAction::PageUpdated)->exists())->toBeFalse();
});

test('deleting a page records a deleted event that survives the page', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create(['title' => 'Legal', 'slug' => 'legal']);

    $this->actingAs($admin)->delete(route('admin.pages.destroy', $page))->assertRedirect();

    $deleted = AuditLog::query()->where('action', AuditAction::PageDeleted)->sole();

    expect($deleted->subject_id)->toBe($page->id)
        ->and($deleted->actor_id)->toBe($admin->id)
        ->and($deleted->changes)->toBe([
            'en.title' => ['old' => 'Legal', 'new' => null],
            'en.slug' => ['old' => 'legal', 'new' => null],
        ]);
});

test('a forbidden mutation records no audit entry', function () {
    $editor = User::factory()->editor()->create();
    $page = Page::factory()->published()->create();

    $this->actingAs($editor)->delete(route('admin.pages.destroy', $page))->assertForbidden();

    expect(AuditLog::query()->count())->toBe(0);
});

test('a role change from the console is recorded without an actor and without secrets', function () {
    $user = User::factory()->editor()->create();

    $this->artisan('app:user-role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();

    $log = AuditLog::query()->sole();
    $serialized = json_encode($log->changes);

    expect($log->action)->toBe(AuditAction::UserRoleChanged)
        ->and($log->actor_id)->toBeNull()
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->changes)->toBe(['role' => ['old' => UserRole::Editor->value, 'new' => UserRole::Admin->value]])
        ->and($serialized)->not->toContain($user->password)
        ->and($serialized)->not->toContain($user->email);

    $this->artisan('app:user-role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(1);
});

test('audit entries cannot be modified or deleted through the model', function () {
    $log = AuditLog::factory()->create();

    expect(fn () => $log->update(['action' => AuditAction::PageDeleted]))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and($log->fresh()?->action)->toBe(AuditAction::PageUpdated);
});

test('an admin browses the audit log filtered by action, newest first', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $older = AuditLog::factory()->for($admin, 'actor')->create(['action' => AuditAction::PageCreated, 'created_at' => now()->subDay()]);
    $newer = AuditLog::factory()->for($admin, 'actor')->create(['action' => AuditAction::PageUpdated]);
    AuditLog::factory()->system()->create(['action' => AuditAction::UserRoleChanged, 'subject_type' => (new User)->getMorphClass()]);

    $this->actingAs($admin)->get(route('admin.audit.index', ['action' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/audit/index', false)
            ->has('items', 3)
            ->where('items.0.subjectType', 'user')
            ->where('items.0.actorName', null)
            ->where('items.1.id', $newer->id)
            ->where('items.1.actorName', 'Ada Admin')
            ->where('items.1.changedFields', ['en.title'])
            ->where('items.2.id', $older->id)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
            ->where('actions', AuditAction::values())
            ->missing('items.0.changes')
            ->where('auth.can.viewAudit', true)
        );

    $this->actingAs($admin)->get(route('admin.audit.index', ['action' => 'page.created']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.id', $older->id)
            ->where('filters.action', 'page.created')
        );

    $this->actingAs($admin)->get(route('admin.audit.index', ['action' => 'secret.read']))
        ->assertSessionHasErrors('action');
});

test('editors and users without a panel role cannot open the audit log', function () {
    AuditLog::factory()->create();

    $editor = User::factory()->editor()->create();
    $this->actingAs($editor)->get(route('admin.audit.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.pages.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('auth.can.viewAudit', false));

    $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.audit.index'))->assertRedirect(route('login'));
});
