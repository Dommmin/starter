<?php

/*
| Online booking embedded from Cal.com or Calendly on `/book-appointment`.
| The page does not exist while no valid address is set. Add it to a menu in
| the panel when you want visitors to find it.
*/

return [

    'url' => env('BOOKING_URL'),

    'allowed_hosts' => ['cal.com', 'app.cal.com', 'calendly.com'],

];
