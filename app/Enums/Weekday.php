<?php

namespace App\Enums;

/**
 * Day of the week of the opening hours, Monday first (ISO 8601).
 */
enum Weekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    /**
     * Schema.org `DayOfWeek` name (e.g. `Monday`) for JSON-LD.
     */
    public function schemaName(): string
    {
        return ucfirst($this->value);
    }
}
