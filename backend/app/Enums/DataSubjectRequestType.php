<?php

namespace App\Enums;

enum DataSubjectRequestType: string
{
    case ACCESS = 'ACCESS';
    case CORRECTION = 'CORRECTION';
    case PORTABILITY = 'PORTABILITY';
    case DELETION = 'DELETION';
    case REVOCATION = 'REVOCATION';
    case INFORMATION = 'INFORMATION';
    case ANONYMIZATION = 'ANONYMIZATION';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
