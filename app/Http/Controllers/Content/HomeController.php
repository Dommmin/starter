<?php

namespace App\Http\Controllers\Content;

use App\Actions\Home\BuildHomeSections;
use App\Data\Contact\ContactFormData;
use App\Data\Content\WelcomePageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Services\Localization\LocalizationManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public home page: the enabled sections of the current locale and the
 * contact form.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, LocalizationManager $localization, BuildHomeSections $buildSections): Response
    {
        return Inertia::render('welcome', new WelcomePageData(
            contactForm: new ContactFormData(
                token: StoreContactMessageRequest::issueFormToken(),
            ),
            sections: $buildSections->handle($localization->getCurrentLocale($request)),
        ));
    }
}
