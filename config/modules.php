<?php

/*
| Optional modules of the starter, chosen per deployment (never in the panel).
| A disabled module disappears from the panel and the public site, but its
| tables and data stay, so it can be turned on again.
|
| The core (accounts, roles, audit, media, settings, pages, SEO) cannot be
| turned off. These switches shape a client's package; they are not licence
| protection on a server the client controls.
*/

return [

    'faq' => (bool) env('MODULE_FAQ', true),

];
