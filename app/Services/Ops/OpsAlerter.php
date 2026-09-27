<?php

namespace App\Services\Ops;

use App\Mail\OpsAlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Operator alerts without an external SaaS (open question Q7). Every alert
 * is a critical `ops.*` log entry (JSON log / journal, picked up by the host
 * monitor). With OPS_ALERT_EMAIL set, a deduplicated plain-text mail is sent
 * synchronously: the queue may be the failing component. A failed mail never
 * throws and never re-enters the alerter (no logging loop).
 *
 * Context must stay PII-free: class names, queue names, counters, ages.
 */
class OpsAlerter
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function critical(string $event, array $context = [], ?string $dedupeKey = null): void
    {
        $event = 'ops.'.$event;

        Log::critical($event, $context);

        $recipient = config('ops.alerts.mail_to');

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        try {
            $ttl = now()->addMinutes(max(1, (int) config('ops.alerts.dedupe_minutes')));

            if (! Cache::add('ops:alert:'.sha1($event.'|'.($dedupeKey ?? '')), true, $ttl)) {
                return;
            }

            Mail::to($recipient)->send(new OpsAlertMail(
                event: $event,
                context: $context,
                environment: (string) config('app.env'),
                release: config('ops.release'),
            ));
        } catch (Throwable $exception) {
            Log::warning('ops.alert_mail_failed', [
                'alert' => $event,
                'exception' => $exception::class,
            ]);
        }
    }
}
