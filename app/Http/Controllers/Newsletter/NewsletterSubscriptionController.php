<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\StoreNewsletterSubscriptionRequest;
use App\Services\Newsletter\NewsletterSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Public newsletter sign-up (throttled, honeypot). The success answer never
 * reveals whether the address was already known or the request was spam.
 */
class NewsletterSubscriptionController extends Controller
{
    public function store(StoreNewsletterSubscriptionRequest $request, NewsletterSignup $signup): RedirectResponse
    {
        abort_unless(NewsletterSignup::isEnabled(), 404);

        if (blank($request->input('website')) && ! $signup->subscribe($request->string('newsletter_email')->trim()->toString())) {
            throw ValidationException::withMessages(['newsletter_email' => __('public.newsletter.unavailable')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('public.newsletter.success')]);

        return back();
    }
}
