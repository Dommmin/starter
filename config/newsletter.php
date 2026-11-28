<?php

/*
| Newsletter sign-up in the public footer, forwarded to MailerLite. The
| application stores no addresses. Turn on double opt-in "for API and
| integrations" in the MailerLite account: the API cannot enforce it.
| The form is hidden while no API key is set.
*/

return [

    'mailerlite' => [
        'api_key' => env('MAILERLITE_API_KEY'),
        'group_id' => env('MAILERLITE_GROUP_ID'),
        'url' => 'https://connect.mailerlite.com/api/subscribers',
    ],

];
