<?php

namespace App\Data\Settings;

use App\Enums\Weekday;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Opening hours of one day as `HH:MM` times; both null when closed.
 */
#[TypeScript]
class OpeningHoursDayData extends Data
{
    public function __construct(
        public Weekday $day,
        public ?string $opens,
        public ?string $closes,
    ) {}

    public function isOpen(): bool
    {
        return $this->opens !== null && $this->closes !== null;
    }

    /**
     * Week from stored rows, Monday first; a missing or malformed day is
     * closed. Null when nothing is stored.
     *
     * @param  list<array{day: string, opens: string|null, closes: string|null}>|null  $rows
     * @return list<self>|null
     */
    public static function week(?array $rows): ?array
    {
        if ($rows === null || $rows === []) {
            return null;
        }

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row;
        }

        $week = [];
        foreach (Weekday::cases() as $day) {
            $row = $byDay[$day->value] ?? null;
            $opens = is_string($row['opens'] ?? null) ? $row['opens'] : null;
            $closes = is_string($row['closes'] ?? null) ? $row['closes'] : null;
            $isOpen = $opens !== null && $closes !== null;

            $week[] = new self(day: $day, opens: $isOpen ? $opens : null, closes: $isOpen ? $closes : null);
        }

        return $week;
    }

    /**
     * @return array{day: string, opens: string|null, closes: string|null}
     */
    public function toRow(): array
    {
        return ['day' => $this->day->value, 'opens' => $this->opens, 'closes' => $this->closes];
    }
}
