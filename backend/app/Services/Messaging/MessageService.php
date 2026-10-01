<?php

namespace App\Services\Messaging;

use App\DTOs\Providers\MessagePayload;
use App\DTOs\Providers\ProviderResult;
use App\Jobs\SendMessageJob;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\MessageLink;
use App\Models\ProviderLog;
use App\Services\Providers\MessageProviderResolver;
use App\Services\Tracking\TrackingProcessor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MessageService
{
    public function __construct(
        protected MessageProviderResolver $resolver,
        protected TrackingProcessor $trackingProcessor
    ) {}

    /**
     * Lista mensagens com paginação e filtros com isolamento por plataforma.
     */
    public function list(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Message::where('platform_id', $platformId)
            ->with(['provider:id,name,channel,driver', 'template:id,name', 'templateVersion:id,version']);

        if (!empty($filters['channel']) && $filters['channel'] !== 'ALL') {
            $query->where('channel', strtoupper($filters['channel']));
        }

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%")
                  ->orWhere('provider_message_id', 'LIKE', "%{$search}%")
                  ->orWhere('idempotency_key', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Busca uma mensagem por ID ou UUID.
     */
    public function getById(int|string $id, int $platformId): Message
    {
        $query = Message::where('platform_id', $platformId)
            ->with(['provider', 'template', 'templateVersion', 'events']);

        if (is_numeric($id)) {
            $message = $query->where('id', (int) $id)->first();
        } else {
            $message = $query->where('uuid', $id)->first();
        }

        if (!$message) {
            throw new InvalidArgumentException("Mensagem não encontrada para a plataforma atual.");
        }

        return $message;
    }

    /**
     * Recupera os eventos da linha do tempo de uma mensagem.
     */
    public function getEvents(int|string $id, int $platformId): array
    {
        $message = $this->getById($id, $platformId);
        return $message->events()->get()->toArray();
    }

    /**
     * Enfileira uma mensagem com verificação estrita de idempotência no Redis e PostgreSQL.
     */
    public function send(int $platformId, MessagePayload $payload, ?int $providerId = null): Message
    {
        $this->validatePayload($payload);

        $idempotencyKey = $payload->idempotencyKey ?: Str::uuid()->toString();
        $payload->idempotencyKey = $idempotencyKey;
        $payload->platformId = $platformId;

        // 1. Idempotência definitiva no PostgreSQL
        $existing = Message::where('platform_id', $platformId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        // 2. Bloqueio rápido no Redis contra rajadas simultâneas
        $redisLockKey = "betcrm:message:idempotency:{$platformId}:{$idempotencyKey}";
        $acquired = false;

        try {
            $acquired = (bool) Redis::set($redisLockKey, 1, 'EX', 30, 'NX');
            if (!$acquired) {
                // Outro worker está processando a mesma chave neste instante
                $recheck = Message::where('platform_id', $platformId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($recheck) {
                    return $recheck;
                }
            }
        } catch (\Throwable) {}

        return DB::transaction(function () use ($platformId, $payload, $providerId, $idempotencyKey, $redisLockKey) {
            // Dupla checagem sob transação
            $existing = Message::where('platform_id', $platformId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $channel = !empty($payload->smsContent) ? 'SMS' : 'EMAIL';

            // Resolve previamente o provedor designado
            $resolved = $this->resolver->resolve($platformId, $channel, $providerId);
            $provider = $resolved['provider'];

            $openTrackingToken = null;
            $unsubscribeToken = null;
            $trackedLinks = [];

            // Tracking avançado exclusivo para e-mails (LGPD e privacidade preservadas)
            if ($channel === 'EMAIL' && !empty($payload->html)) {
                $openTrackingToken = Str::random(48);
                $unsubscribeToken = Str::random(48);
                $baseUrl = config('app.url', 'http://localhost:8000');

                $processed = $this->trackingProcessor->process(
                    $payload->html,
                    $openTrackingToken,
                    $unsubscribeToken,
                    $baseUrl
                );

                $payload->html = $processed['html'];
                $trackedLinks = $processed['links'];
            }

            $playerId = $payload->metadata['player_id'] ?? null;

            $message = Message::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $platformId,
                'campaign_id' => $payload->campaignId,
                'player_id' => $playerId,
                'provider_id' => $provider->id,
                'channel' => $channel,
                'recipient' => $payload->recipient,
                'recipient_name' => $payload->recipientName,
                'subject' => $payload->subject,
                'template_id' => $payload->templateId,
                'template_version_id' => $payload->templateVersionId,
                'status' => 'PENDING',
                'idempotency_key' => $idempotencyKey,
                'open_tracking_token' => $openTrackingToken,
                'unsubscribe_token' => $unsubscribeToken,
                'metadata' => [
                    'html' => $payload->html,
                    'text' => $payload->text,
                    'sms_content' => $payload->smsContent,
                    'custom' => $payload->metadata,
                ],
            ]);

            // Persiste links rastreáveis do e-mail
            foreach ($trackedLinks as $linkData) {
                MessageLink::create([
                    'message_id' => $message->id,
                    'tracking_token' => $linkData['tracking_token'],
                    'destination_url' => $linkData['destination_url'],
                ]);
            }

            MessageEvent::create([
                'message_id' => $message->id,
                'event_type' => 'ACCEPTED',
                'payload' => ['idempotency_key' => $idempotencyKey],
                'occurred_at' => now(),
                'created_at' => now(),
            ]);

            // Dispara para fila assíncrona
            SendMessageJob::dispatch($message->id);

            return $message;
        });
    }

    /**
     * Executa envio síncrono para fins de teste administrativo (/api/v1/messages/test).
     */
    public function sendTest(int $platformId, array $data, ?int $userId = null): array
    {
        $channel = strtoupper($data['channel'] ?? 'EMAIL');
        $recipient = $data['recipient'] ?? '';
        $subject = $data['subject'] ?? 'Teste de Mensagem';
        $content = $data['content'] ?? 'Esta é uma mensagem de teste do BET CRM.';
        $providerId = !empty($data['provider_id']) ? (int) $data['provider_id'] : null;

        $payload = new MessagePayload(
            recipient: $recipient,
            recipientName: 'Usuário Teste',
            subject: $subject,
            html: $channel === 'EMAIL' ? "<p>{$content}</p>" : null,
            text: $channel === 'EMAIL' ? $content : null,
            smsContent: $channel === 'SMS' ? $content : null,
            platformId: $platformId,
            metadata: ['is_test' => true, 'user_id' => $userId],
            idempotencyKey: 'test_' . Str::uuid()->toString()
        );

        $this->validatePayload($payload);

        $resolved = $this->resolver->resolve($platformId, $channel, $providerId);
        $provider = $resolved['provider'];
        $driver = $resolved['driver'];

        $startTime = hrtime(true);
        $result = $driver->send($payload);
        $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

        // Registra log do teste
        ProviderLog::create([
            'platform_id' => $platformId,
            'provider_id' => $provider->id,
            'channel' => $channel,
            'action' => 'test_send',
            'status' => $result->success ? 'SUCCESS' : 'FAILED',
            'provider_message_id' => $result->providerMessageId,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'latency_ms' => $latencyMs,
            'request_metadata' => [
                'recipient' => $recipient,
                'channel' => $channel,
                'is_test' => true,
            ],
            'response_metadata' => $result->rawResponse,
            'created_at' => now(),
        ]);

        // Cria registro de mensagem em estado definitivo
        $message = Message::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $platformId,
            'provider_id' => $provider->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'recipient_name' => 'Usuário Teste',
            'subject' => $subject,
            'status' => $result->success ? 'SENT' : 'FAILED',
            'provider_message_id' => $result->providerMessageId,
            'idempotency_key' => $payload->idempotencyKey,
            'sent_at' => $result->success ? now() : null,
            'failed_at' => $result->success ? null : now(),
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'metadata' => [
                'is_test' => true,
                'latency_ms' => $latencyMs,
            ],
        ]);

        MessageEvent::create([
            'message_id' => $message->id,
            'event_type' => $result->success ? 'SENT' : 'FAILED',
            'provider_event_id' => $result->providerMessageId,
            'payload' => $result->rawResponse,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        return [
            'success' => $result->success,
            'message' => $message,
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'driver' => $provider->driver,
            ],
            'result' => $result->toArray(),
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Retenta o envio de uma mensagem com falha.
     */
    public function retry(int|string $id, int $platformId): Message
    {
        $message = $this->getById($id, $platformId);

        if ($message->status !== 'FAILED') {
            throw new InvalidArgumentException("Apenas mensagens com falha (FAILED) podem ser reenviadas.");
        }

        $message->update([
            'status' => 'PENDING',
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        MessageEvent::create([
            'message_id' => $message->id,
            'event_type' => 'ACCEPTED',
            'payload' => ['action' => 'retry_requested'],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        SendMessageJob::dispatch($message->id);

        return $message->fresh();
    }

    /**
     * Cancela uma mensagem pendente na fila.
     */
    public function cancel(int|string $id, int $platformId): Message
    {
        $message = $this->getById($id, $platformId);

        if (!$message->isPending()) {
            throw new InvalidArgumentException("Apenas mensagens pendentes podem ser canceladas.");
        }

        $message->update([
            'status' => 'CANCELLED',
        ]);

        MessageEvent::create([
            'message_id' => $message->id,
            'event_type' => 'REJECTED',
            'payload' => ['reason' => 'cancelled_by_operator'],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        return $message->fresh();
    }

    protected function validatePayload(MessagePayload $payload): void
    {
        if (empty(trim($payload->recipient))) {
            throw new InvalidArgumentException("O destinatário da mensagem é obrigatório.");
        }

        if (empty($payload->html) && empty($payload->text) && empty($payload->smsContent)) {
            throw new InvalidArgumentException("A mensagem deve conter conteúdo HTML, texto plano ou texto SMS.");
        }
    }
}
