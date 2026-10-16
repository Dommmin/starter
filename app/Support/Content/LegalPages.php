<?php

namespace App\Support\Content;

/**
 * Slugs of the legal pages per locale: seeded by LegalPageSeeder, checked
 * by the launch checklist and linked by the cookie banner
 * (`consent.policy_slugs`).
 */
final class LegalPages
{
    /**
     * @var array<string, array<string, string>>
     */
    public const array SLUGS = [
        'privacy' => ['pl' => 'polityka-prywatnosci', 'en' => 'privacy-policy', 'de' => 'datenschutz'],
        'cookies' => ['pl' => 'polityka-cookies', 'en' => 'cookie-policy', 'de' => 'cookie-richtlinie'],
        'terms' => ['pl' => 'regulamin', 'en' => 'terms-of-service', 'de' => 'agb'],
    ];
}
