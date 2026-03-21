<?php

namespace App\Enums;

enum MatchType: string
{
    case Contains   = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith   = 'ends_with';

    public function label(): string
    {
        return match ($this) {
            self::Contains   => 'Contains',
            self::StartsWith => 'Starts with',
            self::EndsWith   => 'Ends with',
        };
    }
}
