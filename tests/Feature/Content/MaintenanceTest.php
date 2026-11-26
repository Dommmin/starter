<?php

use App\Http\Middleware\ShowMaintenancePage;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('guests get the maintenance page with 503 and Retry-After until the planned end', function (string $url) {
    $this->freezeTime();
    SiteSetting::factory()->create([
        'maintenance' => true,
        'maintenance_until' => now()->addMinutes(90),
        'contact_email' => 'hello@acme.test',
    ]);

    $this->get($url)
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', '5400')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('public/maintenance')
            ->where('contactEmail', 'hello@acme.test')
            ->where('minutesLeft', 90)
        );
})->with(['home' => '/', 'localized home' => '/pl', 'missing page' => '/any-page']);

test('without a planned end or after it the page asks to retry in an hour', function (?int $minutesAgo) {
    SiteSetting::factory()->create([
        'maintenance' => true,
        'maintenance_until' => $minutesAgo === null ? null : now()->subMinutes($minutesAgo),
    ]);

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', (string) ShowMaintenancePage::DEFAULT_RETRY_AFTER)
        ->assertInertia(fn (Assert $inertia) => $inertia->where('minutesLeft', null));
})->with(['no planned end' => null, 'planned end passed' => 5]);

test('public form submissions are paused during maintenance', function () {
    SiteSetting::factory()->create(['maintenance' => true]);

    $this->post(route('contact.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'message' => 'Hello there, this is a message.',
    ])->assertServiceUnavailable();

    expect(ContactMessage::query()->exists())->toBeFalse();
});

test('maintenance takes precedence over the coming soon page', function () {
    SiteSetting::factory()->create(['maintenance' => true, 'coming_soon' => true]);

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertInertia(fn (Assert $inertia) => $inertia->component('public/maintenance'));
});

test('panel users see the whole site during maintenance', function () {
    SiteSetting::factory()->create(['maintenance' => true]);

    $this->actingAs(User::factory()->editor()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->component('welcome'));
});

test('sign-in, health and robots.txt stay reachable during maintenance', function () {
    SiteSetting::factory()->create(['maintenance' => true]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->component('auth/login'));
    $this->get('/robots.txt')->assertOk();
});
