<?php

namespace App\Jobs;

use App\DTOs\Providers\MessagePayload;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\ProviderLog;
use App\Services\Providers\CircuitBreakerService;
use App\Services\Providers\MessageProviderResolver;
use App\Services\Providers\RateLimiterService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de tentativas configurado para 3.
     */
    public int $tries = 3;

    /**
     * Janelas de backoff exponencial: 10s, 60s, 300s.
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $messageId
    ) {
        $this->onQueue('messages');
    }

    public function handle(
        MessageProviderResolver $resolver,
        CircuitBreakerService $circuitBreaker,
        RateLimiterService $rateLimiter
    ): void {
        $message = Message::find($this->messageId);
        if (!$message) {
            return;
        }

        // Se já foi enviada ou cancelada, encerra o job
        if (in_array($message->status, ['SENT', 'CANCELLED'], true)) {
            return;
        }

        $message->update(['status' => 'SENDING']);

        try {
            // 1. Resolve o provedor e seu driver
            $resolved = $resolver->resolve(
                platformId: $message->platform_id,
                channel: $message->channel,
                providerId: $message->provider_id
            );

            $provider = $resolved['provider'];
            $driver = $resolved['driver'];

            // Atualiza provider_id na mensagem caso tenha sido resolvido por default/prioridade
            if ($message->provider_id !== $provider->id) {
                $message->update(['provider_id' => $provider->id]);
            }

            // 2. Verificação de Circuit Breaker
            if (!$circuitBreaker->isAvailable($provider)) {
                Log::warning("[CIRCUIT BREAKER OPEN] Provedor #{$provider->id} em resfriamento. Reagendando mensagem #{$message->id}.");
                $this->release(60);
                return;
            }

            // 3. Verificação de Rate Limit por minuto
            if (!$rateLimiter->attempt($provider)) {
                Log::warning("[RATE LIMIT EXCEEDED] Provedor #{$provider->id} atingiu limite de envio. Reagendando mensagem #{$message->id}.");
                $this->release(30);
                return;
            }

            // 4. Constrói o payload para o driver
            $payload = new MessagePayload(
                recipient: $message->recipient,
                recipientName: $message->recipient_name,
                subject: $message->subject,
                html: $message->metadata['html'] ?? null,
                text: $message->metadata['text'] ?? null,
                smsContent: $message->metadata['sms_content'] ?? null,
                templateId: $message->template_id,
                templateVersionId: $message->template_version_id,
                platformId: $message->platform_id,
                metadata: $message->metadata ?? [],
                idempotencyKey: $message->idempotency_key
            );

            // 5. Executa envio através do driver
            $startTime = hrtime(true);
            $result = $driver->send($payload);
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            // 6. Registro de Auditoria no ProviderLog (Garantindo que nunca salve secrets)
            ProviderLog::create([
                'platform_id' => $message->platform_id,
                'provider_id' => $provider->id,
                'channel' => $message->channel,
                'action' => 'send',
                'status' => $result->success ? 'SUCCESS' : 'FAILED',
                'provider_message_id' => $result->providerMessageId,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'latency_ms' => $latencyMs,
                'request_metadata' => [
                    'message_id' => $message->id,
                    'channel' => $message->channel,
                    'recipient' => $message->masked_recipient,
                ],
                'response_metadata' => $result->rawResponse,
                'created_at' => now(),
            ]);

            // 7. Tratamento de Sucesso
            if ($result->success) {
                $circuitBreaker->recordSuccess($provider);

                $message->update([
                    'status' => 'SENT',
                    'sent_at' => now(),
                    'provider_message_id' => $result->providerMessageId,
                    'error_code' => null,
                    'error_message' => null,
                ]);

                MessageEvent::create([
                    'message_id' => $message->id,
                    'event_type' => 'SENT',
                    'provider_event_id' => $result->providerMessageId,
                    'payload' => [
                        'provider' => $provider->driver,
                        'latency_ms' => $latencyMs,
                    ],
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);

                return;
            }

            // 8. Tratamento de Falha
            $circuitBreaker->recordFailure($provider, $result->errorCode);

            $isRetryable = $this->isRetryableError($result->errorCode);

            // Se for erro não-reintentável ou se atingiu o máximo de tentativas
            if (!$isRetryable || $this->attempts() >= $this->tries) {
                $this->markAsFailed($message, $result->errorCode, $result->errorMessage);
                return;
            }

            // Erro reintentável: lança exceção para a fila do Laravel aplicar a política de backoff
            throw new Exception("Falha temporária no provedor [{$result->errorCode}]: {$result->errorMessage}");

        } catch (Throwable $e) {
            $isRetryable = $this->isRetryableException($e);

            if (!$isRetryable || $this->attempts() >= $this->tries) {
                $this->markAsFailed($message, 'UNEXPECTED_ERROR', $e->getMessage());
                return;
            }

            throw $e;
        }
    }

    /**
     * Classifica se o código de erro retornado pelo provedor é elegível para retry automático.
     */
    protected function isRetryableError(?string $errorCode): bool
    {
        $retryableCodes = [
            'CONNECTION_TIMEOUT',
            'RATE_LIMITED',
            'SERVER_ERROR',
            'HTTP_500',
            'HTTP_502',
            'HTTP_503',
            'HTTP_504',
        ];

        return in_array($errorCode, $retryableCodes, true);
    }

    /**
     * Classifica se a exceção disparada é temporária/reintentável.
     */
    protected function isRetryableException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'timeout')
            || str_contains($message, 'connection')
            || str_contains($message, 'rate_limited')
            || str_contains($message, 'temporária');
    }

    /**
     * Marca a mensagem definitivamente como FAILED e registra evento de falha.
     */
    protected function markAsFailed(Message $message, ?string $code, ?string $reason): void
    {
        $message->update([
            'status' => 'FAILED',
            'failed_at' => now(),
            'error_code' => $code ?: 'FAILED',
            'error_message' => $reason ?: 'Falha desconhecida no envio da mensagem.',
        ]);

        MessageEvent::create([
            'message_id' => $message->id,
            'event_type' => 'FAILED',
            'payload' => [
                'error_code' => $code,
                'error_message' => $reason,
                'attempts' => $this->attempts(),
            ],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }
}
