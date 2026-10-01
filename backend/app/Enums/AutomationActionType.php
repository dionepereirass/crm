<?php

namespace App\Enums;

enum AutomationActionType: string
{
    case SEND_EMAIL = 'SEND_EMAIL';
    case SEND_SMS = 'SEND_SMS';
    case ADD_TAG = 'ADD_TAG';
    case REMOVE_TAG = 'REMOVE_TAG';
    case WAIT = 'WAIT';
    case CHECK_CONDITION = 'CHECK_CONDITION';
    case ENTER_SEGMENT = 'ENTER_SEGMENT';
    case EXIT_SEGMENT = 'EXIT_SEGMENT';

    public function label(): string
    {
        return match($this) {
            self::SEND_EMAIL => 'Enviar E-mail',
            self::SEND_SMS => 'Enviar SMS',
            self::ADD_TAG => 'Adicionar Tag',
            self::REMOVE_TAG => 'Remover Tag',
            self::WAIT => 'Aguardar',
            self::CHECK_CONDITION => 'Verificar Condição',
            self::ENTER_SEGMENT => 'Entrar em Segmento',
            self::EXIT_SEGMENT => 'Sair de Segmento',
        };
    }
}
