<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case GRANTED = 'GRANTED';
    case REVOKED = 'REVOKED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
