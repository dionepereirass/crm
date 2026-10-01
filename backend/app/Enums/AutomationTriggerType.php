<?php

namespace App\Enums;

enum AutomationTriggerType: string
{
    case PLAYER_CREATED = 'PLAYER_CREATED';
    case PLAYER_UPDATED = 'PLAYER_UPDATED';
    case DEPOSIT_SUCCESS = 'DEPOSIT_SUCCESS';
    case BET_PLACED = 'BET_PLACED';
    case BET_SETTLED = 'BET_SETTLED';
    case WITHDRAWAL_SUCCESS = 'WITHDRAWAL_SUCCESS';
    case LOGIN = 'LOGIN';
    case TAG_ADDED = 'TAG_ADDED';
    case TAG_REMOVED = 'TAG_REMOVED';
    case SEGMENT_ENTERED = 'SEGMENT_ENTERED';
    case SEGMENT_EXITED = 'SEGMENT_EXITED';
    case CAMPAIGN_DELIVERED = 'CAMPAIGN_DELIVERED';
    case CAMPAIGN_OPENED = 'CAMPAIGN_OPENED';
    case CAMPAIGN_CLICKED = 'CAMPAIGN_CLICKED';

    public function label(): string
    {
        return match($this) {
            self::PLAYER_CREATED => 'Jogador Cadastrado',
            self::PLAYER_UPDATED => 'Jogador Atualizado',
            self::DEPOSIT_SUCCESS => 'Depósito Realizado',
            self::BET_PLACED => 'Aposta Realizada',
            self::BET_SETTLED => 'Aposta Liquidada',
            self::WITHDRAWAL_SUCCESS => 'Saque Realizado',
            self::LOGIN => 'Login de Jogador',
            self::TAG_ADDED => 'Tag Adicionada',
            self::TAG_REMOVED => 'Tag Removida',
            self::SEGMENT_ENTERED => 'Entrada em Segmento',
            self::SEGMENT_EXITED => 'Saída de Segmento',
            self::CAMPAIGN_DELIVERED => 'Campanha Entregue',
            self::CAMPAIGN_OPENED => 'Campanha Aberta',
            self::CAMPAIGN_CLICKED => 'Link de Campanha Clicado',
        };
    }
}
