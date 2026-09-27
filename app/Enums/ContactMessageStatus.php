<?php

namespace App\Enums;

/**
 * Delivery state of a contact message: `pending` until the mail is accepted
 * by the mailer, `failed` once the job exhausted its attempts.
 */
enum ContactMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
