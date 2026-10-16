<?php

use App\Support\Webhooks\WebhookSignature;

test('a signature verifies only with the same secret, body and a fresh timestamp', function () {
    $now = 1_760_000_000;
    $body = '{"id":1}';
    $signature = WebhookSignature::sign('secret', $now, $body);

    expect($signature)->toStartWith('sha256=')
        ->and(WebhookSignature::verify('secret', $signature, $now, $body, $now + 10))->toBeTrue()
        ->and(WebhookSignature::verify('other-secret', $signature, $now, $body, $now))->toBeFalse()
        ->and(WebhookSignature::verify('secret', $signature, $now, '{"id":2}', $now))->toBeFalse()
        ->and(WebhookSignature::verify('secret', 'sha256=forged', $now, $body, $now))->toBeFalse()
        ->and(WebhookSignature::verify('secret', $signature, $now, $body, $now + WebhookSignature::TOLERANCE_SECONDS + 1))->toBeFalse();
});
