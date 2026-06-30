<?php

namespace App\Traits;

trait HasEnumHelpers
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(static::cases(), 'name', 'value');
    }
}
