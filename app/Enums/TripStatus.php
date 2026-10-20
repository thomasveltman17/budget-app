<?php

namespace App\Enums;

enum TripStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Settling = 'settling';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Settling => 'Settling up',
            self::Settled => 'Settled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Active => 'info',
            self::Settling => 'warning',
            self::Settled => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
