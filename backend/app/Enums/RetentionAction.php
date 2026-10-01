<?php

namespace App\Enums;

enum RetentionAction: string
{
    case DELETE = 'DELETE';
    case ANONYMIZE = 'ANONYMIZE';
    case RETAIN = 'RETAIN';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
