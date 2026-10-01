<?php

namespace App\Enums;

enum AutomationNodeType: string
{
    case TRIGGER = 'TRIGGER';
    case CONDITION = 'CONDITION';
    case ACTION = 'ACTION';
    case WAIT = 'WAIT';

    public function label(): string
    {
        return match($this) {
            self::TRIGGER => 'Gatilho',
            self::CONDITION => 'Condição',
            self::ACTION => 'Ação',
            self::WAIT => 'Espera',
        };
    }
}
