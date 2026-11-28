<?php

namespace App\Services\Newsletter;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * Forwards a sign-up to MailerLite. Logs never contain the address.
 */
class NewsletterSignup
{
    public static function isEnabled(): bool
    {
        return filled(config('newsletter.mailerlite.api_key'));
    }

    /**
     * Only the address and the optional group are sent. `status` is left out
     * on purpose: it would change existing subscribers, and without it the
     * provider refuses (422) to bring back someone who unsubscribed.
     *
     * @return bool False when the provider could not be reached or refused our
     *              credentials, so the visitor should try again later. A 422
     *              counts as accepted: the answer must not reveal whether an
     *              address is already known.
     */
    public function subscribe(#[SensitiveParameter] string $email): bool
    {
        $group = config('newsletter.mailerlite.group_id');

        try {
            $response = Http::withToken((string) config('newsletter.mailerlite.api_key'))
                ->acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->post((string) config('newsletter.mailerlite.url'), [
                    'email' => $email,
                    ...(filled($group) ? ['groups' => [(string) $group]] : []),
                ]);
        } catch (ConnectionException) {
            Log::warning('Newsletter provider unreachable.');

            return false;
        }

        if ($response->successful()) {
            if ($response->status() === 201 && $response->json('data.status') === 'active') {
                Log::warning('Newsletter subscriber became active without confirmation; enable double opt-in for API in MailerLite.');
            }

            return true;
        }

        Log::warning('Newsletter provider rejected a sign-up.', [
            'status' => $response->status(),
            'fields' => array_keys((array) $response->json('errors')),
        ]);

        return $response->status() === 422;
    }
}
