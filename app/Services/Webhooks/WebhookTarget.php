<?php

namespace App\Services\Webhooks;

use InvalidArgumentException;

/**
 * SSRF guard of outgoing webhooks: the URL must be https without
 * credentials and its host must resolve only to globally routable
 * addresses (no private, loopback, link-local, CGNAT or reserved ranges). The checked address is
 * returned so the request can be pinned to it (no DNS rebinding).
 */
final readonly class WebhookTarget
{
    private function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $address,
    ) {}

    /**
     * @throws InvalidArgumentException When the URL or any resolved address is not allowed.
     */
    public static function resolve(string $url, HostResolver $resolver): self
    {
        $parts = parse_url($url);

        if (
            $parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('The webhook URL must be an https URL without credentials.');
        }

        $host = trim($parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $resolver->resolve($host);

        if ($addresses === []) {
            throw new InvalidArgumentException('The webhook host does not resolve.');
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                throw new InvalidArgumentException('The webhook host resolves to a private or reserved address.');
            }
        }

        return new self($url, $host, $parts['port'] ?? 443, $addresses[0]);
    }

    public static function isPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
