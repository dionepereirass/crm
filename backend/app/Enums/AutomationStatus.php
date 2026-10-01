<?php

namespace App\Enums;

enum AutomationStatus: string
{
    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case INACTIVE = 'INACTIVE';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Rascunho',
            self::ACTIVE => 'Ativo',
            self::PAUSED => 'Pausado',
            self::INACTIVE => 'Inativo',
        };
    }
}
