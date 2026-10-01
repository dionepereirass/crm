<?php

namespace App\Enums;

enum AutomationRunStatus: string
{
    case RUNNING = 'RUNNING';
    case WAITING = 'WAITING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case SKIPPED = 'SKIPPED';

    public function label(): string
    {
        return match($this) {
            self::RUNNING => 'Em Execução',
            self::WAITING => 'Aguardando',
            self::COMPLETED => 'Concluída',
            self::FAILED => 'Falhou',
            self::CANCELLED => 'Cancelada',
            self::SKIPPED => 'Ignorada',
        };
    }
}
