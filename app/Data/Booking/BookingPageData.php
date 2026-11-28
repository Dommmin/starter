<?php

namespace App\Data\Booking;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BookingPageData extends Data
{
    public function __construct(
        public string $bookingUrl,
        public string $providerHost,
    ) {}
}
