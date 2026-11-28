<?php

namespace App\Enums;

/**
 * Closed set of modules that config/modules.php can turn off.
 */
enum Module: string
{
    case Faq = 'faq';

    public function isEnabled(): bool
    {
        return config("modules.{$this->value}") === true;
    }

    /**
     * @return list<string>
     */
    public static function disabled(): array
    {
        $disabled = [];

        foreach (self::cases() as $module) {
            if (! $module->isEnabled()) {
                $disabled[] = $module->value;
            }
        }

        return $disabled;
    }
}
