<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\AssertionFailedError;

test('an unknown slug renders the Inertia error page with 404 and noindex', function () {
    $this->get('/missing-page')
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('errors/show')
            ->where('status', 404)
            ->where('i18n.locale', 'en')
            ->has('seo.siteName')
        );
});

test('an unmatched localized url renders the error page in that locale', function () {
    $this->get('/pl/does/not/exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('errors/show')
            ->where('status', 404)
            ->where('i18n.locale', 'pl')
            ->where('i18n.messages.errors.notFound.title', 'Strona nie została znaleziona')
        );
});

test('a user without panel access gets the 403 error page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.index'))
        ->assertForbidden()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('errors/show')
            ->where('status', 403)
        );
});

test('json requests keep the default error response', function () {
    $this->getJson('/missing-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('server errors keep the debug page when debug mode is on', function () {
    config(['app.debug' => true]);
    Route::get('/__boom', fn () => throw new RuntimeException('boom'))->middleware('web');

    $response = $this->get('/__boom')->assertStatus(500);

    expect($response->headers->has('X-Inertia'))->toBeFalse();
    expect(fn () => $response->assertInertia(fn (Assert $inertia) => $inertia->component('errors/show')))
        ->toThrow(AssertionFailedError::class);
});

test('server errors render the error page when debug mode is off', function () {
    config(['app.debug' => false]);
    Route::get('/__boom', fn () => throw new RuntimeException('boom'))->middleware('web');

    $this->get('/__boom')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('errors/show')
            ->where('status', 500)
        );
});
