<?php

namespace App\Services\Booking;

/**
 * The configured booking calendar. Only https addresses of the allowed
 * providers count, because the address is framed and widens the CSP.
 */
class BookingProvider
{
    public static function url(): ?string
    {
        $url = config('booking.url');

        if (! is_string($url)) {
            return null;
        }

        $parts = parse_url($url);

        $allowed = is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, (array) config('booking.allowed_hosts'), true)
            && ! isset($parts['user'])
            && ! isset($parts['port']);

        return $allowed ? $url : null;
    }

    public static function origin(): ?string
    {
        $url = self::url();

        return $url === null ? null : 'https://'.parse_url($url, PHP_URL_HOST);
    }
}
