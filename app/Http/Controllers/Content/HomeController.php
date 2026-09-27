<?php

namespace App\Http\Controllers\Content;

use App\Data\Contact\ContactFormData;
use App\Data\Content\WelcomePageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public home page with the contact form.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', new WelcomePageData(
            contactForm: new ContactFormData(
                token: StoreContactMessageRequest::issueFormToken(),
            ),
        ));
    }
}
