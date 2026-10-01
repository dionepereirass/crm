<?php

namespace App\Enums;

enum ConsentType: string
{
    case MARKETING_EMAIL = 'MARKETING_EMAIL';
    case MARKETING_SMS = 'MARKETING_SMS';
    case MARKETING_WHATSAPP = 'MARKETING_WHATSAPP';
    case MARKETING_PUSH = 'MARKETING_PUSH';
    case TERMS = 'TERMS';
    case PRIVACY_POLICY = 'PRIVACY_POLICY';
    case COOKIES = 'COOKIES';
    case PROFILING = 'PROFILING';

    /**
     * Mapeia canal de mensageria para o tipo de consentimento.
     */
    public static function fromChannel(string $channel): self
    {
        return match (strtoupper($channel)) {
            'EMAIL' => self::MARKETING_EMAIL,
            'SMS' => self::MARKETING_SMS,
            'WHATSAPP' => self::MARKETING_WHATSAPP,
            'PUSH' => self::MARKETING_PUSH,
            default => self::MARKETING_EMAIL,
        };
    }

    /**
     * Mapeia tipo de consentimento para o canal simplificado (para compatibilidade).
     */
    public function toChannel(): string
    {
        return match ($this) {
            self::MARKETING_EMAIL => 'EMAIL',
            self::MARKETING_SMS => 'SMS',
            self::MARKETING_WHATSAPP => 'WHATSAPP',
            self::MARKETING_PUSH => 'PUSH',
            default => $this->value,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
