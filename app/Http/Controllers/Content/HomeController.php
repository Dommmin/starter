<?php

namespace App\Http\Controllers\Content;

use App\Actions\Home\BuildHomeSections;
use App\Data\Contact\ContactFormData;
use App\Data\Content\WelcomePageData;
use App\Data\Home\ContactContentData;
use App\Data\Home\HomeSectionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Repositories\Contact\ContactFormRepository;
use App\Repositories\Settings\SiteSettingsRepository;
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
    public function __invoke(Request $request, LocalizationManager $localization, BuildHomeSections $buildSections, SiteSettingsRepository $siteSettings, ContactFormRepository $contactForms): Response
    {
        $locale = $localization->getCurrentLocale($request);
        $sections = $buildSections->handle($locale, $request->user());

        return Inertia::render('welcome', new WelcomePageData(
            contactForm: new ContactFormData(
                token: StoreContactMessageRequest::issueFormToken(),
                form: $contactForms->publicForm(self::contactFormKey($sections), $locale),
            ),
            sections: $sections,
            organizationJsonLd: $siteSettings->organizationSchema(),
        ));
    }

    /**
     * Form of the first contact section (one form per page).
     *
     * @param  list<HomeSectionData>  $sections
     */
    private static function contactFormKey(array $sections): ?string
    {
        foreach ($sections as $section) {
            if ($section->content instanceof ContactContentData) {
                return $section->content->formKey;
            }
        }

        return null;
    }
}
