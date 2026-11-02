<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Vite;

const CSP_REPORT_ONLY_HEADER = 'Content-Security-Policy-Report-Only';

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('public pages send the policy in report-only mode without unsafe sources', function () {
    $response = $this->get(route('home'))->assertOk();

    $policy = (string) $response->headers->get(CSP_REPORT_ONLY_HEADER);

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($policy)->toContain("default-src 'self'")
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("base-uri 'self'")
        ->and($policy)->toContain("frame-ancestors 'none'")
        ->and($policy)->toContain("form-action 'self'")
        ->and($policy)->toContain("img-src 'self' data: blob:")
        ->and($policy)->toContain("connect-src 'self'")
        ->and($policy)->not->toContain('unsafe-eval')
        ->and($policy)->not->toContain('unsafe-inline');
});

test('the admin panel sends the same report-only policy', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get(route('admin.index'))->assertOk();

    expect((string) $response->headers->get(CSP_REPORT_ONLY_HEADER))
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-eval');
});

test('inline scripts and styles of the root template carry the nonce from the policy', function () {
    $response = $this->get(route('home'))->assertOk();

    preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get(CSP_REPORT_ONLY_HEADER), $matches);

    expect($matches)->toHaveCount(2);

    $nonce = $matches[1];
    $html = $response->getContent();

    expect(Vite::cspNonce())->toBe($nonce)
        ->and($html)->toContain('<script nonce="'.$nonce.'">')
        ->and($html)->toContain('<style nonce="'.$nonce.'">')
        ->and($html)->toContain('<meta property="csp-nonce" nonce="'.$nonce.'">')
        ->and((string) $response->headers->get(CSP_REPORT_ONLY_HEADER))->toContain("style-src 'self' 'nonce-{$nonce}'");
});

test('configured third party origins and the report endpoint extend the policy', function () {
    config([
        'csp.script_src' => 'https://analytics.example',
        'csp.connect_src' => 'https://analytics.example',
        'csp.report_uri' => '/csp-report',
    ]);

    $policy = (string) $this->get(route('home'))->assertOk()->headers->get(CSP_REPORT_ONLY_HEADER);

    expect($policy)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+' https:\/\/analytics\.example(;| )/")
        ->and($policy)->toContain("connect-src 'self' https://analytics.example")
        ->and($policy)->toContain('report-uri /csp-report');
});

test('a configured plausible script origin is allowed for its script and events', function () {
    config([
        'services.plausible.domain' => 'starter.example',
        'services.plausible.script_url' => 'https://plausible.example:8443/js/script.js',
    ]);

    $policy = (string) $this->get(route('home'))->assertOk()->headers->get(CSP_REPORT_ONLY_HEADER);

    expect($policy)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+' https:\/\/plausible\.example:8443(;| )/")
        ->and($policy)->toContain("connect-src 'self' https://plausible.example:8443");
});

test('plausible without a domain or over plain http does not widen the policy', function (?string $domain, string $scriptUrl) {
    config([
        'services.plausible.domain' => $domain,
        'services.plausible.script_url' => $scriptUrl,
    ]);

    $policy = (string) $this->get(route('home'))->assertOk()->headers->get(CSP_REPORT_ONLY_HEADER);

    expect($policy)->not->toContain('plausible.example');
})->with([
    'no domain' => [null, 'https://plausible.example/js/script.js'],
    'plain http' => ['starter.example', 'http://plausible.example/js/script.js'],
]);

test('a plausible domain saved in the panel allows the environment script origin', function () {
    config([
        'services.plausible.domain' => null,
        'services.plausible.script_url' => 'https://plausible.example/js/script.js',
    ]);
    SiteSetting::factory()->create(['plausible_domain' => 'starter.example']);

    $policy = (string) $this->get(route('home'))->assertOk()->headers->get(CSP_REPORT_ONLY_HEADER);

    expect($policy)->toContain("connect-src 'self' https://plausible.example");
});

test('the policy is enforced once report-only mode is disabled', function () {
    config(['csp.report_only' => false]);

    $response = $this->get(route('home'))->assertOk();

    expect($response->headers->has(CSP_REPORT_ONLY_HEADER))->toBeFalse()
        ->and((string) $response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
});
