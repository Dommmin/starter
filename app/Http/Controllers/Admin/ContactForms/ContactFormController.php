<?php

namespace App\Http\Controllers\Admin\ContactForms;

use App\Actions\Audit\ShareRecordHistory;
use App\Actions\ContactForms\SaveContactForm;
use App\Data\Admin\ContactForms\ContactFormEditorData;
use App\Data\Admin\ContactForms\ContactFormFormData;
use App\Data\Admin\ContactForms\ContactFormIndexData;
use App\Data\Admin\ContactForms\ContactFormListItemData;
use App\Data\Content\ContentLocalesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactForms\SaveContactFormRequest;
use App\Models\ContactForm;
use App\Models\User;
use App\Repositories\Contact\ContactFormRepository;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD of contact forms (administrators only; `can` middleware with
 * ContactFormPolicy on every route, re-checked by the save request).
 */
class ContactFormController extends Controller
{
    public function __construct(
        private readonly LocalizationConfig $localization,
        private readonly ContactFormRepository $contactForms,
    ) {}

    public function index(Request $request): Response
    {
        $locale = app()->getLocale();
        $fallback = $this->localization->getPublicFallback();

        return Inertia::render('admin/contact-forms/index', new ContactFormIndexData(
            items: array_values($this->contactForms->all()->map(
                fn (ContactForm $form): ContactFormListItemData => ContactFormListItemData::fromModel($form, $locale, $fallback),
            )->all()),
            canCreate: $request->user()?->can('create', ContactForm::class) ?? false,
        ));
    }

    public function create(): Response
    {
        return Inertia::render('admin/contact-forms/create', $this->editor(
            ContactFormFormData::blank($this->localization->getPublicLocales()),
            canDelete: false,
        ));
    }

    public function store(SaveContactFormRequest $request, SaveContactForm $saveContactForm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $form = $saveContactForm->handle(null, $request->formValues(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.contactForms.created')]);

        return to_route('admin.contact-forms.edit', $form);
    }

    public function edit(Request $request, ContactForm $contactForm, ShareRecordHistory $recordHistory): Response
    {
        $recordHistory->handle($request->user(), $contactForm);

        return Inertia::render('admin/contact-forms/edit', $this->editor(
            ContactFormFormData::fromModel($contactForm->load('fields'), $this->localization->getPublicLocales()),
            canDelete: $request->user()?->can('delete', $contactForm) ?? false,
        ));
    }

    /**
     * Update the form; a stale `updated_at` yields a `conflict` error.
     */
    public function update(SaveContactFormRequest $request, ContactForm $contactForm, SaveContactForm $saveContactForm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saveContactForm->handle($contactForm, $request->formValues(), $user, $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.contactForms.updated')]);

        return to_route('admin.contact-forms.edit', $contactForm);
    }

    /**
     * Delete a form other than the default one; its messages stay and
     * sections that showed it fall back to the default form.
     */
    public function destroy(Request $request, ContactForm $contactForm, SaveContactForm $saveContactForm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saveContactForm->delete($contactForm, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.contactForms.deleted')]);

        return to_route('admin.contact-forms.index');
    }

    private function editor(ContactFormFormData $form, bool $canDelete): ContactFormEditorData
    {
        return new ContactFormEditorData(
            form: $form,
            locales: ContentLocalesData::fromConfig($this->localization),
            canDelete: $canDelete,
            maxFields: SaveContactFormRequest::MAX_FIELDS,
            maxOptions: SaveContactFormRequest::MAX_OPTIONS,
        );
    }
}
