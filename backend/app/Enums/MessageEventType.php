<?php

namespace App\Enums;

enum MessageEventType: string
{
    case QUEUED = 'QUEUED';
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case BOUNCED = 'BOUNCED';
    case REJECTED = 'REJECTED';
    case OPENED = 'OPENED';
    case CLICKED = 'CLICKED';
    case UNSUBSCRIBED = 'UNSUBSCRIBED';
    case UNKNOWN = 'UNKNOWN';

    /**
     * Mapeia qualquer string ou evento bruto de provedor para o enum interno seguro.
     */
    public static function fromRaw(?string $raw): self
    {
        if (empty($raw)) {
            return self::UNKNOWN;
        }

        $normalized = strtoupper(trim($raw));

        return match ($normalized) {
            'QUEUED', 'PENDING' => self::QUEUED,
            'SENDING', 'PROCESSING' => self::SENDING,
            'SENT', 'SUCCESS' => self::SENT,
            'DELIVERED', 'SENT_TO_CARRIER', 'DELIVERED_TO_DEVICE' => self::DELIVERED,
            'FAILED', 'UNDELIVERED', 'ERROR' => self::FAILED,
            'BOUNCED', 'HARD_BOUNCE', 'SOFT_BOUNCE', 'SPAM' => self::BOUNCED,
            'REJECTED', 'BLOCKED' => self::REJECTED,
            'OPENED', 'UNIQUE_OPENED', 'OPEN' => self::OPENED,
            'CLICKED', 'CLICK' => self::CLICKED,
            'UNSUBSCRIBED', 'OPTOUT', 'UNSUB' => self::UNSUBSCRIBED,
            default => self::tryFrom($normalized) ?? self::UNKNOWN,
        };
    }

    /**
     * Nível de precedência na transição de status da mensagem.
     * Estados com maior precedência não podem ser revertidos por estados de menor precedência.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::QUEUED => 10,
            self::SENDING => 20,
            self::SENT => 30,
            self::DELIVERED => 40,
            self::OPENED, self::CLICKED, self::UNSUBSCRIBED => 45,
            self::FAILED, self::BOUNCED, self::REJECTED => 50,
            self::UNKNOWN => 0,
        };
    }

    /**
     * Determina se o evento representa interação/engajamento do usuário.
     */
    public function isEngagement(): bool
    {
        return in_array($this, [self::OPENED, self::CLICKED, self::UNSUBSCRIBED], true);
    }

    /**
     * Determina se o evento representa falha de entrega.
     */
    public function isFailure(): bool
    {
        return in_array($this, [self::FAILED, self::BOUNCED, self::REJECTED], true);
    }
}
