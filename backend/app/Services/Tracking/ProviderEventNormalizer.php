<?php

namespace App\Services\Tracking;

use App\Enums\MessageEventType;
use Carbon\Carbon;

class ProviderEventNormalizer
{
    /**
     * Normaliza payloads heterogêneos de diferentes provedores de mensageria
     * em uma estrutura padronizada e segura para o BET CRM.
     *
     * @param string $driver Identificador do driver (brevo, zenvia, fake_email, fake_sms, etc.)
     * @param array $payload Dados recebidos no corpo do webhook
     * @param array $headers Cabeçalhos HTTP relevantes
     * @return array{
     *     event_type: MessageEventType,
     *     provider_event_id: string,
     *     provider_message_id: ?string,
     *     occurred_at: Carbon,
     *     raw_event: string,
     *     error_code: ?string,
     *     error_message: ?string,
     *     metadata: array
     * }
     */
    public function normalize(string $driver, array $payload, array $headers = []): array
    {
        $driverNormalized = strtolower(trim($driver));

        return match ($driverNormalized) {
            'brevo' => $this->normalizeBrevo($payload, $headers),
            'zenvia' => $this->normalizeZenvia($payload, $headers),
            default => $this->normalizeGeneric($driverNormalized, $payload, $headers),
        };
    }

    /**
     * Normaliza webhook do Brevo (Sendinblue).
     */
    protected function normalizeBrevo(array $payload, array $headers): array
    {
        $rawEvent = strtolower($payload['event'] ?? $payload['type'] ?? 'unknown');

        $eventType = match ($rawEvent) {
            'delivered' => MessageEventType::DELIVERED,
            'request', 'sent' => MessageEventType::SENT,
            'hard_bounce', 'soft_bounce', 'blocked', 'spam' => MessageEventType::BOUNCED,
            'error', 'deferred' => MessageEventType::FAILED,
            'opened', 'unique_opened' => MessageEventType::OPENED,
            'click', 'clicks' => MessageEventType::CLICKED,
            'unsubscribed', 'optout' => MessageEventType::UNSUBSCRIBED,
            'rejected' => MessageEventType::REJECTED,
            default => MessageEventType::fromRaw($rawEvent),
        };

        $providerMessageId = $payload['message-id']
            ?? $payload['messageId']
            ?? $payload['message_id']
            ?? null;

        $providerEventId = $payload['event_id']
            ?? $payload['id']
            ?? $headers['x-event-id'][0]
            ?? null;

        // Limpa possíveis marcadores angulares comuns no Brevo (<uuid@domain>)
        if ($providerMessageId) {
            $providerMessageId = trim($providerMessageId, '<>');
        }

        $occurredAt = $this->parseTimestamp($payload['date'] ?? $payload['ts_event'] ?? $payload['timestamp'] ?? null);

        if (!$providerEventId) {
            $providerEventId = $this->generateDeterministicEventId('brevo', $providerMessageId, $rawEvent, $occurredAt);
        }

        $errorCode = $payload['reason'] ?? $payload['error'] ?? null;
        $errorMessage = $payload['description'] ?? $payload['error_description'] ?? null;

        return [
            'event_type' => $eventType,
            'provider_event_id' => (string) $providerEventId,
            'provider_message_id' => $providerMessageId ? (string) $providerMessageId : null,
            'occurred_at' => $occurredAt,
            'raw_event' => $rawEvent,
            'error_code' => $errorCode ? (string) $errorCode : null,
            'error_message' => $errorMessage ? (string) $errorMessage : null,
            'metadata' => $this->sanitizeMetadata($payload),
        ];
    }

    /**
     * Normaliza webhook do Zenvia.
     */
    protected function normalizeZenvia(array $payload, array $headers): array
    {
        $rawEvent = strtoupper($payload['type'] ?? $payload['event'] ?? 'UNKNOWN');

        $eventType = match ($rawEvent) {
            'MESSAGE_SENT', 'SENT' => MessageEventType::SENT,
            'MESSAGE_DELIVERED', 'DELIVERED' => MessageEventType::DELIVERED,
            'MESSAGE_NOT_DELIVERED', 'NOT_DELIVERED' => MessageEventType::FAILED,
            'MESSAGE_REJECTED', 'REJECTED' => MessageEventType::REJECTED,
            'MESSAGE_BOUNCED' => MessageEventType::BOUNCED,
            default => MessageEventType::fromRaw($rawEvent),
        };

        $providerMessageId = $payload['messageId']
            ?? $payload['message_id']
            ?? $payload['id']
            ?? null;

        $providerEventId = $payload['eventId']
            ?? $payload['event_id']
            ?? $payload['id']
            ?? null;

        $occurredAt = $this->parseTimestamp($payload['timestamp'] ?? $payload['occurredAt'] ?? null);

        if (!$providerEventId) {
            $providerEventId = $this->generateDeterministicEventId('zenvia', $providerMessageId, $rawEvent, $occurredAt);
        }

        $cause = $payload['cause'] ?? $payload['reason'] ?? null;

        return [
            'event_type' => $eventType,
            'provider_event_id' => (string) $providerEventId,
            'provider_message_id' => $providerMessageId ? (string) $providerMessageId : null,
            'occurred_at' => $occurredAt,
            'raw_event' => $rawEvent,
            'error_code' => $cause ? (string) $cause : null,
            'error_message' => $payload['description'] ?? null,
            'metadata' => $this->sanitizeMetadata($payload),
        ];
    }

    /**
     * Normaliza webhook genérico / simulado / fake providers.
     */
    protected function normalizeGeneric(string $driver, array $payload, array $headers): array
    {
        $rawEvent = strtolower($payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? $payload['status'] ?? 'unknown');
        $eventType = MessageEventType::fromRaw($rawEvent);

        $providerMessageId = $payload['provider_message_id']
            ?? $payload['message_id']
            ?? $payload['messageId']
            ?? $payload['id']
            ?? null;

        $providerEventId = $payload['provider_event_id']
            ?? $payload['event_id']
            ?? $payload['eventId']
            ?? $headers['x-event-id'][0]
            ?? null;

        $occurredAt = $this->parseTimestamp($payload['occurred_at'] ?? $payload['timestamp'] ?? $payload['date'] ?? null);

        if (!$providerEventId) {
            $providerEventId = $this->generateDeterministicEventId($driver, $providerMessageId, $rawEvent, $occurredAt);
        }

        return [
            'event_type' => $eventType,
            'provider_event_id' => (string) $providerEventId,
            'provider_message_id' => $providerMessageId ? (string) $providerMessageId : null,
            'occurred_at' => $occurredAt,
            'raw_event' => $rawEvent,
            'error_code' => $payload['error_code'] ?? null,
            'error_message' => $payload['error_message'] ?? null,
            'metadata' => $this->sanitizeMetadata($payload),
        ];
    }

    /**
     * Faz o parse seguro de timestamps variados (ISO 8601, Unix epoch em ms ou seg, data formatada).
     */
    protected function parseTimestamp(mixed $timestamp): Carbon
    {
        if (empty($timestamp)) {
            return now();
        }

        try {
            if (is_numeric($timestamp)) {
                // Se for em milissegundos (> 10 dígitos)
                if ($timestamp > 9999999999) {
                    return Carbon::createFromTimestampMs($timestamp);
                }
                return Carbon::createFromTimestamp($timestamp);
            }

            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Gera hash determinístico SHA-256 quando o provedor não disponibiliza ID de evento único.
     */
    protected function generateDeterministicEventId(
        string $driver,
        ?string $messageId,
        string $event,
        Carbon $occurredAt
    ): string {
        $input = "{$driver}:{$messageId}:{$event}:" . $occurredAt->toIso8601String();
        return 'evt_' . hash('sha256', $input);
    }

    /**
     * Higieniza dados sensíveis antes de salvar no banco ou logar.
     */
    protected function sanitizeMetadata(array $data): array
    {
        $sensitiveKeys = [
            'password', 'token', 'secret', 'api_key', 'apikey', 'auth', 'authorization',
            'cpf', 'credit_card', 'pin', 'credentials', 'bearer'
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
