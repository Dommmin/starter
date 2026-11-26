<?php

namespace App\Enums;

/**
 * Category of a contact message suggested by the AI assistant (stage 6);
 * null on the message until it is classified. Spam is only marked, never
 * deleted.
 */
enum ContactMessageCategory: string
{
    case Lead = 'lead';
    case Question = 'question';
    case Spam = 'spam';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
