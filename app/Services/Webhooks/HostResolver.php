<?php

namespace App\Services\Webhooks;

/**
 * DNS resolution of webhook hosts (A and AAAA records). A separate class so
 * tests can resolve hosts without the network.
 */
class HostResolver
{
    /**
     * @return list<string> IPv4 and IPv6 addresses; empty when unresolvable.
     */
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
