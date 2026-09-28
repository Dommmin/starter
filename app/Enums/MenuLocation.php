<?php

namespace App\Enums;

/**
 * Place of the public site where a navigation menu is rendered.
 */
enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
