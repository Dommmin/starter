<?php

namespace App\Http\Controllers\Contact;

use App\Actions\Contact\SubmitContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Public contact form endpoint (rate limited by the `contact` limiter).
 * The response never reveals whether the mail was delivered, nor whether
 * the submission was discarded as spam.
 */
class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request, SubmitContactMessage $submitContactMessage): RedirectResponse
    {
        if (! $request->isSpam()) {
            $submitContactMessage->handle(
                name: $request->string('name')->trim()->toString(),
                email: $request->string('email')->trim()->toString(),
                message: $request->string('message')->toString(),
                locale: app()->getLocale(),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('public.contact.success')]);

        return back();
    }
}
