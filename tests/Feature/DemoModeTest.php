<?php

use App\Models\Article;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * Turn the demo on for this test and register its route, which web.php
 * adds only when the demo is on at boot.
 */
function enableDemo(): void
{
    config(['demo.enabled' => true]);
    Route::middleware('web')->group(base_path('routes/demo.php'));
    Route::getRoutes()->refreshNameLookups();
}

test('without the demo there is no one-click demo sign-in and changes work', function () {
    $this->post('/_demo/login')->assertNotFound();
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demoLoginUrl', null));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.faqs.store'), ['question' => 'Q?', 'answer' => 'A.', 'published' => true])->assertRedirect();

    expect(Faq::query()->count())->toBe(1);
});

test('on the demo every change is refused for everyone, with a toast or a 403', function () {
    enableDemo();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.faqs.create'))
        ->post(route('admin.faqs.store'), ['question' => 'Q?', 'answer' => 'A.', 'published' => true])
        ->assertRedirect(route('admin.faqs.create'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('common.demo.blocked')]);

    $this->actingAs($admin)
        ->postJson(route('admin.faqs.store'), ['question' => 'Q?', 'answer' => 'A.', 'published' => true])
        ->assertForbidden()
        ->assertJsonPath('message', __('common.demo.blocked'));

    $this->actingAs($admin)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'new-Password-123', 'password_confirmation' => 'new-Password-123',
    ])->assertRedirect();

    auth()->logout();
    $this->post(route('contact.store'), ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi'])->assertRedirect();
    $this->post(route('register.store'), ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Secure-Password-2026!', 'password_confirmation' => 'Secure-Password-2026!']);

    expect(Faq::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

test('on the demo visitors sign in with one click as the sample administrator and out again', function () {
    enableDemo();
    $this->seed(DatabaseSeeder::class);
    $demoAdmin = User::query()->where('email', DemoContent::USER_EMAIL)->sole();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demoLoginUrl', route('demo.login')));

    $this->post(route('demo.login'))->assertRedirect(route('admin.index'));
    $this->assertAuthenticatedAs($demoAdmin);

    $this->get(route('admin.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('demo', true));

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
})->skip(fn () => ! extension_loaded('gd'), 'The demo seed creates sample images.');

test('signing in with a password still works on the demo', function () {
    enableDemo();
    $user = User::factory()->create(['email' => 'editor@example.com']);

    $this->post(route('login.store'), ['email' => 'editor@example.com', 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('the demo seeds sample content outside the local environment', function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('media');
    Storage::fake((string) config('media.public_disk'));
    $this->app['env'] = 'production';
    config(['demo.enabled' => true]);

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->where('email', DemoContent::USER_EMAIL)->exists())->toBeTrue()
        ->and(Article::query()->count())->toBeGreaterThan(0);
});

test('demo:reset refuses to run outside the demo and changes nothing', function () {
    $faq = Faq::factory()->create();

    $this->artisan('demo:reset')
        ->expectsOutputToContain('demo:reset runs only on the public demo')
        ->assertFailed();

    expect(Faq::query()->whereKey($faq->id)->exists())->toBeTrue();
});
