<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the booking page frames an allowed provider and the policy allows only it', function () {
    config(['booking.url' => 'https://cal.com/example/visit']);

    $response = $this->get(route('booking'))->assertOk()->assertInertia(fn (Assert $assert) => $assert
        ->component('public/booking')
        ->where('providerHost', 'cal.com'));

    $policy = $response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only');
    expect($policy)->toContain("frame-src 'self' https://cal.com");
});

test('a missing, insecure or unknown address closes the page and keeps the policy narrow', function (?string $url) {
    config(['booking.url' => $url]);

    $response = $this->get(route('booking'))->assertNotFound();

    $policy = $response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only');
    expect($policy)->not->toContain('frame-src');
})->with([null, 'http://cal.com/example', 'https://evil.example/cal.com', 'https://cal.com.evil.example/x', 'https://user@cal.com/x', 'javascript:alert(1)']);
