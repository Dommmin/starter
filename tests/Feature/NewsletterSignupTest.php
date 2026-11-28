<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['newsletter.mailerlite.api_key' => 'test-key', 'newsletter.mailerlite.group_id' => '42']);
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function signupPayload(array $overrides = []): array
{
    return ['newsletter_email' => 'reader@example.com', 'newsletter_consent' => true, 'website' => '', ...$overrides];
}

test('a sign-up sends only the address and group, never a status', function () {
    Http::fake(['connect.mailerlite.com/*' => Http::response(['data' => ['status' => 'unconfirmed']], 201)]);

    $this->post(route('newsletter.store'), signupPayload())->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->data() === ['email' => 'reader@example.com', 'groups' => ['42']]
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

test('an address the provider refuses gets the same answer as a new one', function () {
    Http::fake(['connect.mailerlite.com/*' => Http::response(['errors' => ['status' => ['unsubscribed']]], 422)]);

    $this->post(route('newsletter.store'), signupPayload())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('public.newsletter.success')]);
});

test('an unreachable or failing provider asks the visitor to try again', function (Closure $response) {
    Http::fake($response);

    $this->post(route('newsletter.store'), signupPayload())->assertSessionHasErrors('newsletter_email');
})->with([
    'server error' => fn (Request $request) => Http::response([], 500),
    'wrong key' => fn (Request $request) => Http::response([], 401),
    'timeout' => fn (Request $request) => throw new ConnectionException('timeout'),
]);

test('bots and missing consent never reach the provider', function () {
    Http::fake();

    $this->post(route('newsletter.store'), signupPayload(['website' => 'https://spam.example']))->assertSessionHasNoErrors();
    $this->post(route('newsletter.store'), signupPayload(['newsletter_consent' => false]))->assertSessionHasErrors('newsletter_consent');

    Http::assertNothingSent();
});

test('without an API key the form is hidden and the address is closed', function () {
    config(['newsletter.mailerlite.api_key' => null]);
    Http::fake();

    $this->get(route('home'))->assertInertia(fn (Assert $assert) => $assert->missing('newsletter'));
    $this->post(route('newsletter.store'), signupPayload())->assertNotFound();

    // Not assertNothingSent(): with an SSR bundle built, rendering the home page
    // calls the SSR server through the same HTTP client.
    Http::assertNotSent(fn (Request $request): bool => $request->url() === config('newsletter.mailerlite.url'));
});
