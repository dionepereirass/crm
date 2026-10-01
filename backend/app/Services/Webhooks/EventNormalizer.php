<?php

namespace App\Services\Webhooks;

use Carbon\Carbon;
use InvalidArgumentException;

class EventNormalizer
{
    /**
     * Mapeamento de nomes de eventos externos comuns para chaves internas padronizadas.
     */
    protected array $eventMap = [
        'player.created' => 'PLAYER_CREATED',
        'player_created' => 'PLAYER_CREATED',
        'user.created' => 'PLAYER_CREATED',
        'user.registered' => 'PLAYER_CREATED',
        'registration' => 'PLAYER_CREATED',

        'player.updated' => 'PLAYER_UPDATED',
        'player_updated' => 'PLAYER_UPDATED',
        'user.updated' => 'PLAYER_UPDATED',

        'deposit.success' => 'DEPOSIT_SUCCESS',
        'deposit_success' => 'DEPOSIT_SUCCESS',
        'deposit.approved' => 'DEPOSIT_SUCCESS',
        'deposit.completed' => 'DEPOSIT_SUCCESS',
        'deposit' => 'DEPOSIT_SUCCESS',

        'bet.placed' => 'BET_PLACED',
        'bet_placed' => 'BET_PLACED',
        'wager.placed' => 'BET_PLACED',
        'bet' => 'BET_PLACED',

        'bet.settled' => 'BET_SETTLED',
        'bet_settled' => 'BET_SETTLED',
        'wager.settled' => 'BET_SETTLED',
        'bet.result' => 'BET_SETTLED',

        'withdrawal.success' => 'WITHDRAWAL_SUCCESS',
        'withdrawal_success' => 'WITHDRAWAL_SUCCESS',
        'payout.approved' => 'WITHDRAWAL_SUCCESS',
        'withdrawal' => 'WITHDRAWAL_SUCCESS',

        'login' => 'LOGIN',
        'player.login' => 'LOGIN',
        'player_login' => 'LOGIN',
        'user.login' => 'LOGIN',
    ];

    /**
     * Normaliza o payload externo recebido para a estrutura interna consistente.
     */
    public function normalize(array $payload): array
    {
        // 1. Extração do Tipo de Evento
        $rawEvent = $this->extractRawEventType($payload);
        $eventTypeKey = $this->resolveEventTypeKey($rawEvent);

        // 2. Extração do External Event ID
        $externalEventId = $this->extractExternalEventId($payload);
        if (empty($externalEventId)) {
            throw new InvalidArgumentException('Não foi possível identificar o ID do evento externo (external_event_id).');
        }

        // 3. Extração do External Player ID
        $externalPlayerId = $this->extractExternalPlayerId($payload);

        // 4. Extração e conversão de timestamp para UTC
        $occurredAt = $this->extractOccurredAt($payload);

        // 5. Normalização de dados financeiros e adicionais
        $normalizedData = $this->normalizeDataPayload($eventTypeKey, $payload);

        return [
            'event_type' => $eventTypeKey,
            'external_event_id' => (string) $externalEventId,
            'external_player_id' => $externalPlayerId ? (string) $externalPlayerId : null,
            'occurred_at' => $occurredAt,
            'normalized_payload' => [
                'event_type' => $eventTypeKey,
                'external_event_id' => (string) $externalEventId,
                'player' => [
                    'external_id' => $externalPlayerId ? (string) $externalPlayerId : null,
                    'name' => $payload['player']['name'] ?? $payload['name'] ?? null,
                    'email' => $payload['player']['email'] ?? $payload['email'] ?? null,
                    'phone' => $payload['player']['phone'] ?? $payload['phone'] ?? null,
                ],
                'data' => $normalizedData,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        ];
    }

    protected function extractRawEventType(array $payload): string
    {
        return $payload['event']
            ?? $payload['event_type']
            ?? $payload['type']
            ?? $payload['name']
            ?? $payload['action']
            ?? '';
    }

    public function resolveEventTypeKey(string $rawEvent): string
    {
        $normalized = strtolower(trim($rawEvent));
        if (isset($this->eventMap[$normalized])) {
            return $this->eventMap[$normalized];
        }

        // Se já vier em formato uppercase separado por underscore
        $upper = strtoupper(str_replace(['.', '-'], '_', trim($rawEvent)));
        if (!empty($upper)) {
            return $upper;
        }

        return 'UNKNOWN_EVENT';
    }

    public function extractExternalEventId(array $payload): ?string
    {
        $id = $payload['event_id']
            ?? $payload['id']
            ?? $payload['external_id']
            ?? $payload['external_event_id']
            ?? $payload['transaction_id']
            ?? $payload['message_id']
            ?? $payload['data']['event_id']
            ?? $payload['data']['id']
            ?? null;

        return $id ? (string) $id : null;
    }

    public function extractExternalPlayerId(array $payload): ?string
    {
        $id = $payload['player']['external_id']
            ?? $payload['player']['id']
            ?? $payload['player_id']
            ?? $payload['external_player_id']
            ?? $payload['user_id']
            ?? $payload['customer_id']
            ?? $payload['data']['player_id']
            ?? $payload['data']['external_id']
            ?? null;

        return $id ? (string) $id : null;
    }

    protected function extractOccurredAt(array $payload): Carbon
    {
        $rawTime = $payload['timestamp']
            ?? $payload['occurred_at']
            ?? $payload['created_at']
            ?? $payload['date']
            ?? null;

        if (empty($rawTime)) {
            return Carbon::now('UTC');
        }

        try {
            if (is_numeric($rawTime)) {
                // Unix timestamp em segundos ou milissegundos
                return strlen((string)$rawTime) >= 13
                    ? Carbon::createFromTimestampMs((int)$rawTime, 'UTC')
                    : Carbon::createFromTimestamp((int)$rawTime, 'UTC');
            }
            return Carbon::parse($rawTime, 'UTC');
        } catch (\Throwable $e) {
            return Carbon::now('UTC');
        }
    }

    protected function normalizeDataPayload(string $eventType, array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        // Limpa campos de controle de topo para o bloco data
        unset($data['event'], $data['event_type'], $data['type'], $data['event_id']);

        // Normalização financeira estrita: nunca FLOAT, sempre string formatada com 2 casas decimais
        if (isset($data['amount']) && is_numeric($data['amount'])) {
            $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        }

        if (isset($data['win_amount']) && is_numeric($data['win_amount'])) {
            $data['win_amount'] = number_format((float) $data['win_amount'], 2, '.', '');
        }

        if (isset($data['bet_amount']) && is_numeric($data['bet_amount'])) {
            $data['bet_amount'] = number_format((float) $data['bet_amount'], 2, '.', '');
        }

        if (isset($data['fee']) && is_numeric($data['fee'])) {
            $data['fee'] = number_format((float) $data['fee'], 2, '.', '');
        }

        return $data;
    }
}
