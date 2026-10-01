<?php

namespace App\Services\Tracking;

use App\Enums\MessageEventType;
use App\Jobs\ProcessMessageEventJob;
use App\Models\Consent;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\MessageLink;
use App\Models\Player;
use App\Services\Analytics\CampaignAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MessageEventService
{
    public function __construct(
        protected CampaignAnalyticsService $analyticsService
    ) {}

    /**
     * Processa um evento previamente normalizado e o persiste com garantia de
     * idempotência, respeito à precedência de status e atualização de analytics.
     *
     * @param array $eventData Dados retornados por ProviderEventNormalizer
     * @param ?int $providerId ID do provedor no banco (se resolvido)
     */
    public function handleNormalizedEvent(array $eventData, ?int $providerId = null): ?MessageEvent
    {
        $providerMessageId = $eventData['provider_message_id'] ?? null;
        $providerEventId = $eventData['provider_event_id'] ?? null;
        $eventType = $eventData['event_type'] instanceof MessageEventType
            ? $eventData['event_type']
            : MessageEventType::fromRaw($eventData['event_type'] ?? 'UNKNOWN');

        // 1. Idempotência estrita: se provider_event_id já existe, retorna o evento existente
        if ($providerEventId) {
            $existingEvent = MessageEvent::where('provider_event_id', (string) $providerEventId)->first();
            if ($existingEvent) {
                return $existingEvent;
            }
        }

        // 2. Localiza a mensagem correspondente
        $message = null;
        if ($providerMessageId) {
            $message = Message::where('provider_message_id', (string) $providerMessageId)->first();
        }

        if (!$message && !empty($eventData['message_id'])) {
            $message = Message::find($eventData['message_id']);
        }

        if (!$message) {
            Log::info("[MESSAGE EVENT] Mensagem não localizada para o evento", [
                'provider_message_id' => $providerMessageId,
                'event_type' => $eventType->value,
                'provider_event_id' => $providerEventId,
            ]);
            return null;
        }

        $occurredAt = $eventData['occurred_at'] instanceof Carbon
            ? $eventData['occurred_at']
            : ($eventData['occurred_at'] ? Carbon::parse($eventData['occurred_at']) : now());

        // 3. Atualiza o status da mensagem respeitando precedência
        $this->updateMessageStatus($message, $eventType, $eventData, $occurredAt);

        // 4. Persiste o evento na tabela message_events
        $event = MessageEvent::create([
            'message_id' => $message->id,
            'provider_id' => $providerId ?? $message->provider_id,
            'event_type' => $eventType->value,
            'provider_event_id' => $providerEventId ? (string) $providerEventId : null,
            'payload' => $eventData['metadata'] ?? [],
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);

        // 5. Atualiza métricas agregadas da campanha (se a mensagem pertencer a uma campanha)
        if ($message->campaign_id) {
            if (config('queue.default') === 'sync') {
                $this->analyticsService->recordEvent($message->campaign_id, $eventType, $message->id);
            } else {
                ProcessMessageEventJob::dispatch($message->campaign_id, $eventType->value, $message->id);
            }
        }

        // 6. Dispara automações vinculadas a eventos de mensagens/campanhas (Fase 10)
        try {
            app(\App\Services\Automations\AutomationTriggerService::class)->handleMessageEvent($event);
        } catch (\Throwable) {}

        return $event;
    }

    /**
     * Registra evento de abertura via pixel 1x1 transparente (Email Open Tracking).
     */
    public function recordOpen(string $openToken, array $requestInfo = []): ?MessageEvent
    {
        $message = Message::where('open_tracking_token', $openToken)->first();
        if (!$message) {
            return null;
        }

        // Sanitização de dados de rede (LGPD: IP truncado, sem PII)
        $ip = $requestInfo['ip'] ?? '';
        $maskedIp = $this->maskIp($ip);
        $userAgent = substr($requestInfo['user_agent'] ?? 'Generic Webmail Client', 0, 150);

        $eventData = [
            'event_type' => MessageEventType::OPENED,
            'provider_message_id' => $message->provider_message_id,
            'message_id' => $message->id,
            'provider_event_id' => null, // Evento interno
            'occurred_at' => now(),
            'metadata' => [
                'type' => 'tracking_pixel',
                'masked_ip' => $maskedIp,
                'user_agent' => $userAgent,
                'is_bot_prefetched' => $this->detectPotentialBot($userAgent),
            ],
        ];

        return $this->handleNormalizedEvent($eventData, $message->provider_id);
    }

    /**
     * Registra evento de clique em hiperlink rastreável (Click Tracking).
     *
     * @return array{event: ?MessageEvent, destination_url: ?string}
     */
    public function recordClick(string $trackingToken, array $requestInfo = []): array
    {
        $link = MessageLink::with('message')->where('tracking_token', $trackingToken)->first();
        if (!$link || !$link->message) {
            return ['event' => null, 'destination_url' => null];
        }

        $link->recordClick();
        $message = $link->message;

        $ip = $requestInfo['ip'] ?? '';
        $maskedIp = $this->maskIp($ip);
        $userAgent = substr($requestInfo['user_agent'] ?? '', 0, 150);

        $eventData = [
            'event_type' => MessageEventType::CLICKED,
            'provider_message_id' => $message->provider_message_id,
            'message_id' => $message->id,
            'provider_event_id' => null,
            'occurred_at' => now(),
            'metadata' => [
                'link_id' => $link->id,
                'destination_url' => $link->destination_url,
                'clicks_count' => $link->clicks_count,
                'masked_ip' => $maskedIp,
                'user_agent' => $userAgent,
            ],
        ];

        $event = $this->handleNormalizedEvent($eventData, $message->provider_id);

        return [
            'event' => $event,
            'destination_url' => $link->destination_url,
        ];
    }

    /**
     * Registra descadastramento (Unsubscribe) e revoga consentimento de marketing do jogador.
     */
    public function recordUnsubscribe(string $unsubscribeToken, array $requestInfo = []): array
    {
        $message = Message::where('unsubscribe_token', $unsubscribeToken)->first();
        if (!$message) {
            return [
                'success' => false,
                'message' => 'Token de descadastramento inválido ou expirado.',
            ];
        }

        $eventData = [
            'event_type' => MessageEventType::UNSUBSCRIBED,
            'provider_message_id' => $message->provider_message_id,
            'message_id' => $message->id,
            'provider_event_id' => null,
            'occurred_at' => now(),
            'metadata' => [
                'channel' => $message->channel,
                'recipient' => $message->masked_recipient,
                'ip' => $this->maskIp($requestInfo['ip'] ?? ''),
            ],
        ];

        $this->handleNormalizedEvent($eventData, $message->provider_id);

        // Revoga consentimento de marketing na plataforma
        $player = null;
        if ($message->player_id) {
            $player = Player::find($message->player_id);
        }

        if (!$player && !empty($message->recipient)) {
            $player = Player::where('platform_id', $message->platform_id)
                ->where(function ($q) use ($message) {
                    $q->where('email', $message->recipient)
                      ->orWhere('phone', $message->recipient);
                })
                ->first();
        }

        if ($player) {
            Consent::updateOrCreate(
                [
                    'player_id' => $player->id,
                    'channel' => $message->channel,
                ],
                [
                    'is_granted' => false,
                    'consent_source' => 'UNSUBSCRIBE_LINK',
                    'revoked_at' => now(),
                ]
            );
        }

        return [
            'success' => true,
            'message' => 'Você foi descadastrado com sucesso deste canal de comunicação.',
        ];
    }

    /**
     * Aplica regras estritas de transição de status na mensagem para evitar regressão indevida.
     */
    protected function updateMessageStatus(
        Message $message,
        MessageEventType $eventType,
        array $eventData,
        Carbon $occurredAt
    ): void {
        $currentStatus = $message->status;

        // Se o evento for de entrega ou engajamento posterior
        if ($eventType === MessageEventType::DELIVERED) {
            if (in_array($currentStatus, ['PENDING', 'QUEUED', 'SENDING', 'SENT'], true)) {
                $message->update(['status' => 'DELIVERED']);
            }
        } elseif ($eventType === MessageEventType::OPENED) {
            $updates = [];
            if (in_array($currentStatus, ['PENDING', 'QUEUED', 'SENDING', 'SENT'], true)) {
                $updates['status'] = 'DELIVERED';
            }
            if (!$message->opened_at) {
                $updates['opened_at'] = $occurredAt;
            }
            if (!empty($updates)) {
                $message->update($updates);
            }
        } elseif ($eventType === MessageEventType::CLICKED) {
            $updates = [];
            if (in_array($currentStatus, ['PENDING', 'QUEUED', 'SENDING', 'SENT'], true)) {
                $updates['status'] = 'DELIVERED';
            }
            if (!$message->clicked_at) {
                $updates['clicked_at'] = $occurredAt;
            }
            if (!empty($updates)) {
                $message->update($updates);
            }
        } elseif ($eventType === MessageEventType::SENT) {
            if (in_array($currentStatus, ['PENDING', 'QUEUED', 'SENDING'], true)) {
                $message->update([
                    'status' => 'SENT',
                    'sent_at' => $occurredAt,
                ]);
            }
        } elseif ($eventType->isFailure()) {
            // Falha definitiva: FAILED, BOUNCED ou REJECTED
            if (!in_array($currentStatus, ['FAILED', 'BOUNCED', 'REJECTED'], true)) {
                $message->update([
                    'status' => $eventType->value,
                    'failed_at' => $occurredAt,
                    'error_code' => $eventData['error_code'] ?? "PROVIDER_{$eventType->value}",
                    'error_message' => $eventData['error_message'] ?? "Evento do provedor: {$eventType->value}",
                ]);
            }
        }
    }

    /**
     * Mascara o endereço IP (LGPD).
     */
    protected function maskIp(string $ip): string
    {
        if (empty($ip)) {
            return '';
        }

        // IPv4: 192.168.1.50 -> 192.168.1.xxx
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.xxx';
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return substr($ip, 0, 16) . '::xxxx';
        }

        return 'xxx.xxx.xxx.xxx';
    }

    /**
     * Detecta clientes de e-mail e proxies que realizam pré-carregamento automatizado de links/imagens.
     */
    protected function detectPotentialBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }

        $botSignatures = [
            'googleimageproxy',
            'yahoo! slurp',
            'bot',
            'crawler',
            'spider',
            'facebookexternalhit',
            'whatsapp',
            'bingbot',
            'slackbot',
        ];

        $lowerUa = strtolower($userAgent);
        foreach ($botSignatures as $sig) {
            if (str_contains($lowerUa, $sig)) {
                return true;
            }
        }

        return false;
    }
}
