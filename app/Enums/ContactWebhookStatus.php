<?php

namespace App\Enums;

/**
 * Webhook delivery state of a contact message; null on the message when no
 * webhook is configured.
 */
enum ContactWebhookStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
