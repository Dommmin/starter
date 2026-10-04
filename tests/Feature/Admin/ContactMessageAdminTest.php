<?php

use App\Enums\ContactMessageStatus;
use App\Jobs\SendContactMessage;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    Queue::fake();
});

test('guests are redirected to the login page', function () {
    $this->get(route('admin.contact.index'))->assertRedirect(route('login'));
});

test('users without a panel role are denied', function () {
    $user = User::factory()->create();
    $message = ContactMessage::factory()->failed()->create();

    $this->actingAs($user)->get(route('admin.contact.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.contact.show', $message))->assertForbidden();
    $this->actingAs($user)->post(route('admin.contact.retry', $message))->assertForbidden();

    Queue::assertNothingPushed();
    expect($message->refresh()->status)->toBe(ContactMessageStatus::Failed);
});

test('editors see the typed list without message bodies', function () {
    $editor = User::factory()->editor()->create();
    $message = ContactMessage::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($editor)->get(route('admin.contact.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/contact/index')
            ->has('items', 1)
            ->where('items.0', [
                'id' => $message->id,
                'name' => 'Ada Lovelace',
                'email' => $message->email,
                'locale' => 'en',
                'status' => 'pending',
                'attempts' => 0,
                'createdAt' => $message->created_at?->toIso8601String(),
            ])
            ->where('filters', [
                'search' => '',
                'sort' => 'created_at',
                'direction' => 'desc',
                'status' => 'all',
            ])
        );
});

test('the list searches by name or email and filters by status', function () {
    $admin = User::factory()->admin()->create();
    $failed = ContactMessage::factory()->failed()->create(['name' => 'Needle Person', 'email' => 'x@example.test']);
    ContactMessage::factory()->sent()->create(['name' => 'Other', 'email' => 'needle@example.test']);
    ContactMessage::factory()->create(['name' => 'Someone', 'email' => 'y@example.test']);

    $this->actingAs($admin)->get(route('admin.contact.index', ['search' => 'needle']))
        ->assertInertia(fn (Assert $page) => $page->has('items', 2));

    $this->actingAs($admin)->get(route('admin.contact.index', ['status' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $failed->id)
        );

    $this->actingAs($admin)->get(route('admin.contact.index', ['status' => 'unknown']))
        ->assertSessionHasErrors('status');
});

test('editors can view a message but cannot retry or delete it', function () {
    $editor = User::factory()->editor()->create();
    $message = ContactMessage::factory()->failed()->create();

    $this->actingAs($editor)->get(route('admin.contact.show', $message))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/contact/show')
            ->where('contactMessage.id', $message->id)
            ->where('contactMessage.message', $message->message)
            ->where('contactMessage.status', 'failed')
            ->where('can', ['retry' => false, 'delete' => false])
        );

    $this->actingAs($editor)->post(route('admin.contact.retry', $message))->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.contact.destroy', $message))->assertForbidden();

    Queue::assertNothingPushed();
    expect($message->refresh()->status)->toBe(ContactMessageStatus::Failed)
        ->and(ContactMessage::query()->whereKey($message->id)->exists())->toBeTrue();
});

test('admins can retry a failed message', function () {
    $admin = User::factory()->admin()->create();
    $message = ContactMessage::factory()->failed()->create();

    $this->actingAs($admin)->get(route('admin.contact.show', $message))
        ->assertInertia(fn (Assert $page) => $page->where('can', ['retry' => true, 'delete' => true]));

    $this->actingAs($admin)->post(route('admin.contact.retry', $message))
        ->assertRedirect(route('admin.contact.show', $message))
        ->assertInertiaFlash('toast.type', 'success');

    expect($message->refresh()->status)->toBe(ContactMessageStatus::Pending);
    Queue::assertPushed(SendContactMessage::class, fn (SendContactMessage $job) => $job->contactMessageId === $message->id);
});

test('retrying a sent message is refused without dispatching', function () {
    $admin = User::factory()->admin()->create();
    $message = ContactMessage::factory()->sent()->create();

    $this->actingAs($admin)->post(route('admin.contact.retry', $message))
        ->assertRedirect(route('admin.contact.show', $message))
        ->assertInertiaFlash('toast.type', 'error');

    Queue::assertNothingPushed();
    expect($message->refresh()->status)->toBe(ContactMessageStatus::Sent);
});

test('admins can delete a message', function () {
    $admin = User::factory()->admin()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($admin)->delete(route('admin.contact.destroy', $message))
        ->assertRedirect(route('admin.contact.index'));

    expect(ContactMessage::query()->whereKey($message->id)->exists())->toBeFalse();
});

test('the list tells only administrators that they may delete messages', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.contact.index'))
        ->assertInertia(fn (Assert $page) => $page->where('can', ['delete' => true]));

    $this->actingAs(User::factory()->editor()->create())->get(route('admin.contact.index'))
        ->assertInertia(fn (Assert $page) => $page->where('can', ['delete' => false]));
});

test('admins delete the selected messages and keep the others', function () {
    $admin = User::factory()->admin()->create();
    [$first, $second, $kept] = ContactMessage::factory()->count(3)->create();

    $this->actingAs($admin)
        ->from(route('admin.contact.index', ['page' => 2]))
        ->delete(route('admin.contact.destroy-many'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect(route('admin.contact.index', ['page' => 2]))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', __('admin.contact.deletedMany', ['count' => 2]));

    expect(ContactMessage::query()->pluck('id')->all())->toBe([$kept->id]);
});

test('repeating a bulk delete skips messages that are already gone', function () {
    $admin = User::factory()->admin()->create();
    $message = ContactMessage::factory()->create();
    $missingId = $message->id + 1000;

    $this->actingAs($admin)
        ->delete(route('admin.contact.destroy-many'), ['ids' => [$message->id, $missingId]])
        ->assertInertiaFlash('toast.message', __('admin.contact.deletedMany', ['count' => 1]));

    $this->actingAs($admin)
        ->delete(route('admin.contact.destroy-many'), ['ids' => [$message->id]])
        ->assertInertiaFlash('toast.message', __('admin.contact.deletedMany', ['count' => 0]));
});

test('editors cannot bulk delete and nothing is removed', function () {
    $editor = User::factory()->editor()->create();
    $messages = ContactMessage::factory()->count(2)->create();

    $this->actingAs($editor)
        ->delete(route('admin.contact.destroy-many'), ['ids' => $messages->modelKeys()])
        ->assertForbidden();

    expect(ContactMessage::query()->count())->toBe(2);
});

test('users without a panel role cannot reach the bulk delete', function () {
    $message = ContactMessage::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('admin.contact.destroy-many'), ['ids' => [$message->id]])
        ->assertForbidden();

    expect(ContactMessage::query()->whereKey($message->id)->exists())->toBeTrue();
});

test('a bulk delete needs a bounded list of distinct ids', function (array $payload) {
    $admin = User::factory()->admin()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.contact.destroy-many'), $payload)
        ->assertSessionHasErrors();

    expect(ContactMessage::query()->whereKey($message->id)->exists())->toBeTrue();
})->with([
    'missing ids' => [[]],
    'empty ids' => [['ids' => []]],
    'not integers' => [['ids' => ['abc']]],
    'duplicates' => [['ids' => [1, 1]]],
    'more than one page' => [['ids' => range(1, 101)]],
]);
