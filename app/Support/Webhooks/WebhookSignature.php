<?php

namespace App\Support\Webhooks;

/**
 * HMAC-SHA256 signature of outgoing webhooks: `sha256=` followed by the hex
 * HMAC of "{timestamp}.{body}". Receivers verify it with the same secret
 * and reject stale timestamps (replay); verify() is the reference.
 */
final class WebhookSignature
{
    /** Maximum age (and clock skew) of a signed request, in seconds. */
    public const int TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    public static function verify(string $secret, string $signature, int $timestamp, string $body, int $now): bool
    {
        if (abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), $signature);
    }
}
