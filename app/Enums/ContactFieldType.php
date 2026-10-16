<?php

namespace App\Enums;

/**
 * Type of an extra contact form field. Validation and rendering exist for
 * these types only; a new type is a code change, not a setting.
 */
enum ContactFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkbox = 'checkbox';

    /**
     * Maximum length of a submitted text value.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Textarea => 2000,
            default => 255,
        };
    }
}
