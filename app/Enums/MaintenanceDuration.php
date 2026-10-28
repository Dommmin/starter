<?php

namespace App\Enums;

/**
 * Expected length of the maintenance works chosen on the System page; sets
 * the planned end (and the `Retry-After` header) from the moment of saving.
 */
enum MaintenanceDuration: string
{
    case Unknown = 'unknown';
    case FifteenMinutes = '15';
    case ThirtyMinutes = '30';
    case OneHour = '60';
    case TwoHours = '120';
    case FourHours = '240';
    case OneDay = '1440';

    /**
     * Length in minutes; null when the end is not known.
     */
    public function minutes(): ?int
    {
        return $this === self::Unknown ? null : (int) $this->value;
    }
}
