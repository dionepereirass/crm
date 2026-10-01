<?php

namespace App\Enums;

enum AutomationStepStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';
    case WAITING = 'WAITING';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::COMPLETED => 'Concluído',
            self::FAILED => 'Falhou',
            self::SKIPPED => 'Ignorado',
            self::WAITING => 'Aguardando',
        };
    }
}
