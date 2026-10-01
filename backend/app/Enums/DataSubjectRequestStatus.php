<?php

namespace App\Enums;

enum DataSubjectRequestStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
