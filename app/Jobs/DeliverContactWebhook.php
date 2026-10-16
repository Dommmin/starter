<?php

namespace App\Jobs;

use App\Enums\ContactWebhookStatus;
use App\Models\ContactMessage;
use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookTarget;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * POST one contact message to the configured webhook (contact.webhook).
 *
 * The target is checked against SSRF on every attempt and the request is
 * pinned to the checked address (CURLOPT_RESOLVE) without redirects. The
 * body is signed (WebhookSignature) and carries an idempotency key, so the
 * receiver can drop duplicates. A delivered message is never sent again;
 * failures only change `webhook_status`, never the message itself.
 */
class DeliverContactWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 7200;

    public function __construct(public readonly int $contactMessageId) {}

    public function uniqueId(): string
    {
        return (string) $this->contactMessageId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    /**
     * Whether a webhook URL and secret are configured.
     */
    public static function isConfigured(): bool
    {
        return is_string(config('contact.webhook.url')) && config('contact.webhook.url') !== ''
            && is_string(config('contact.webhook.secret')) && config('contact.webhook.secret') !== '';
    }

    public function handle(HostResolver $resolver): void
    {
        $contactMessage = ContactMessage::query()->with('form.fields')->find($this->contactMessageId);

        if ($contactMessage === null || $contactMessage->webhook_delivered_at !== null || ! self::isConfigured()) {
            return;
        }

        $contactMessage->forceFill([
            'webhook_status' => ContactWebhookStatus::Pending,
            'webhook_attempts' => $contactMessage->webhook_attempts + 1,
        ])->save();

        $target = WebhookTarget::resolve((string) config('contact.webhook.url'), $resolver);
        $body = (string) json_encode(self::payload($contactMessage), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();

        $response = Http::withHeaders([
            'X-Starter-Event' => 'contact_message.created',
            'X-Starter-Delivery' => "contact-message-{$contactMessage->id}",
            'X-Starter-Timestamp' => (string) $timestamp,
            'X-Starter-Signature' => WebhookSignature::sign((string) config('contact.webhook.secret'), $timestamp, $body),
        ])
            ->withBody($body, 'application/json')
            ->withoutRedirecting()
            ->connectTimeout(5)
            ->timeout(max(1, (int) config('contact.webhook.timeout_seconds')))
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target->host}:{$target->port}:{$target->address}"]]])
            ->post($target->url);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook answered HTTP {$response->status()}.");
        }

        $contactMessage->forceFill([
            'webhook_status' => ContactWebhookStatus::Delivered,
            'webhook_delivered_at' => now(),
        ])->save();
    }

    /**
     * All attempts of this dispatch failed; the message stays as it is.
     */
    public function failed(?Throwable $exception): void
    {
        ContactMessage::query()
            ->whereKey($this->contactMessageId)
            ->whereNull('webhook_delivered_at')
            ->update(['webhook_status' => ContactWebhookStatus::Failed->value, 'updated_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(ContactMessage $contactMessage): array
    {
        return [
            'event' => 'contact_message.created',
            'id' => $contactMessage->id,
            'created_at' => $contactMessage->created_at?->toIso8601String(),
            'locale' => $contactMessage->locale,
            'name' => $contactMessage->name,
            'email' => $contactMessage->email,
            'message' => $contactMessage->message,
            'form' => $contactMessage->form?->key,
            'fields' => (object) ($contactMessage->fields ?? []),
        ];
    }
}
