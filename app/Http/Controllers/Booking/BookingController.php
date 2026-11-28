<?php

namespace App\Http\Controllers\Booking;

use App\Data\Booking\BookingPageData;
use App\Http\Controllers\Controller;
use App\Services\Booking\BookingProvider;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __invoke(): Response
    {
        $url = BookingProvider::url();

        abort_if($url === null, 404);

        return Inertia::render('public/booking', new BookingPageData(
            bookingUrl: $url,
            providerHost: (string) parse_url($url, PHP_URL_HOST),
        ));
    }
}
