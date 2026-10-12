<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cookie consent
    |--------------------------------------------------------------------------
    |
    | The banner of public pages stores the visitor's choice in the
    | `cookie_consent` cookie for `lifetime_days`. Raising `version` asks
    | every visitor again, e.g. after the cookie policy changed. Necessary
    | cookies (session, CSRF, theme) need no consent and are not listed.
    |
    | `categories` maps an optional category to its services; a category is
    | offered only while at least one of its services is configured, so the
    | banner never asks about something the site does not use.
    |
    */

    'version' => 1,

    'lifetime_days' => 365,

    'categories' => [
        'analytics' => ['plausible'],
        'marketing' => [],
    ],

    /*
    | Slugs of the page linked from the banner, per locale, in order of
    | preference; the first published one wins and no link is shown without
    | any.
    */

    'policy_slugs' => [
        'pl' => ['polityka-cookies', 'polityka-prywatnosci'],
        'en' => ['cookie-policy', 'privacy-policy'],
        'de' => ['cookie-richtlinie', 'datenschutz'],
    ],

];
