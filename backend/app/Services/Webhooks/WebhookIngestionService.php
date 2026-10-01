<?php

namespace App\Services\Webhooks;

use App\Enums\EventProcessingStatus;
use App\Jobs\ProcessWebhookJob;
use App\Models\Platform;
use App\Models\WebhookLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class WebhookIngestionService
{
    public function __construct(
        protected WebhookSecurityService $security,
        protected IdempotencyService $idempotency,
        protected EventNormalizer $normalizer
    ) {}

    /**
     * Ingestão rápida, segura e assíncrona de webhooks externos.
     */
    public function ingest(string $platformSlug, Request $request): array
    {
        $rawBody = $request->getContent();
        $receivedAt = Carbon::now('UTC');
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $headers = $this->security->maskSensitiveHeaders($request->headers->all());

        // 0. Limite de tamanho de payload (máximo 512KB para proteção contra DoS)
        if (strlen($rawBody) > 524288) {
            throw new HttpException(413, 'Payload do webhook excede o limite máximo permitido de 512KB.');
        }

        // 1. Identifica e valida a plataforma
        $platform = Platform::where('slug', $platformSlug)->first();
        if (!$platform || !$platform->isActive()) {
            throw new NotFoundHttpException("Plataforma '{$platformSlug}' não encontrada ou inativa.");
        }

        // 1.1 Proteção contra Replay Attack via Timestamp (quando presente, janela máxima de 5 min)
        $providedTimestamp = $request->header('X-Webhook-Timestamp') ?? $request->header('x-webhook-timestamp');
        if ($providedTimestamp) {
            $ts = is_numeric($providedTimestamp) ? (int) $providedTimestamp : strtotime($providedTimestamp);
            if ($ts !== false && abs(time() - $ts) > 300) {
                WebhookLog::create([
                    'platform_id' => $platform->id,
                    'endpoint' => $request->path(),
                    'signature_valid' => false,
                    'processing_status' => EventProcessingStatus::INVALID_SIGNATURE,
                    'http_status' => 401,
                    'payload' => $request->json()->all() ?: ['raw' => substr($rawBody, 0, 1000)],
                    'headers' => $headers,
                    'error_message' => 'Janela de tempo do webhook expirada (replay attack prevention).',
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'received_at' => $receivedAt,
                    'processed_at' => $receivedAt,
                ]);

                throw new UnauthorizedHttpException('HMAC', 'Timestamp do webhook fora da janela permitida (5 minutos).');
            }
        }

        // 2. Valida a assinatura HMAC-SHA256 sobre o RAW BODY
        $providedSignature = $request->header('X-Webhook-Signature') ?? $request->header('x-webhook-signature');
        $isSignatureValid = $this->security->validateSignature($rawBody, $providedSignature, $platform);

        if (!$isSignatureValid) {
            // Registra tentativa com assinatura inválida para auditoria e segurança
            WebhookLog::create([
                'platform_id' => $platform->id,
                'endpoint' => $request->path(),
                'signature_valid' => false,
                'processing_status' => EventProcessingStatus::INVALID_SIGNATURE,
                'http_status' => 401,
                'payload' => $request->json()->all() ?: ['raw' => substr($rawBody, 0, 1000)],
                'headers' => $headers,
                'error_message' => 'Assinatura HMAC-SHA256 inválida ou ausente.',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'received_at' => $receivedAt,
                'processed_at' => $receivedAt,
            ]);

            throw new UnauthorizedHttpException('HMAC', 'Assinatura HMAC-SHA256 inválida.');
        }

        // 3. Validação básica de JSON do payload
        $payload = $request->json()->all();
        if (empty($payload) || !is_array($payload)) {
            WebhookLog::create([
                'platform_id' => $platform->id,
                'endpoint' => $request->path(),
                'signature_valid' => true,
                'processing_status' => EventProcessingStatus::INVALID_PAYLOAD,
                'http_status' => 422,
                'payload' => ['raw' => substr($rawBody, 0, 1000)],
                'headers' => $headers,
                'error_message' => 'Payload JSON inválido ou vazio.',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'received_at' => $receivedAt,
                'processed_at' => $receivedAt,
            ]);

            throw new HttpException(422, 'Payload JSON inválido ou vazio.');
        }

        // 4. Extração do External Event ID para verificação de Idempotência
        $externalEventId = $this->normalizer->extractExternalEventId($payload);
        if (empty($externalEventId)) {
            WebhookLog::create([
                'platform_id' => $platform->id,
                'endpoint' => $request->path(),
                'signature_valid' => true,
                'processing_status' => EventProcessingStatus::INVALID_PAYLOAD,
                'http_status' => 422,
                'payload' => $payload,
                'headers' => $headers,
                'error_message' => 'Identificador do evento (external_event_id) ausente no payload.',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'received_at' => $receivedAt,
                'processed_at' => $receivedAt,
            ]);

            throw new HttpException(422, 'Identificador do evento (external_event_id) ausente no payload.');
        }

        // 5. Verificação de Duplicidade (Idempotência)
        if ($this->idempotency->isDuplicate($platform->id, $externalEventId)) {
            $duplicateLog = WebhookLog::create([
                'platform_id' => $platform->id,
                'external_event_id' => $externalEventId,
                'endpoint' => $request->path(),
                'signature_valid' => true,
                'processing_status' => EventProcessingStatus::DUPLICATE,
                'http_status' => 202,
                'payload' => $payload,
                'headers' => $headers,
                'error_message' => 'Evento duplicado detectado pela camada de idempotência.',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'received_at' => $receivedAt,
                'processed_at' => $receivedAt,
            ]);

            return [
                'status_code' => 202,
                'body' => [
                    'success' => true,
                    'message' => 'Evento duplicado já registrado anteriormente.',
                    'status' => 'DUPLICATE',
                    'external_event_id' => $externalEventId,
                    'webhook_id' => $duplicateLog->uuid,
                ],
            ];
        }

        // 6. Registra no Redis para bloquear rajadas simultâneas imediatas
        $this->idempotency->markAsReceived($platform->id, $externalEventId);

        // 7. Persiste o WebhookLog como RECEIVED
        $webhookLog = WebhookLog::create([
            'platform_id' => $platform->id,
            'external_event_id' => $externalEventId,
            'endpoint' => $request->path(),
            'signature_valid' => true,
            'processing_status' => EventProcessingStatus::RECEIVED,
            'http_status' => 202,
            'payload' => $payload,
            'headers' => $headers,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'received_at' => $receivedAt,
        ]);

        // 8. Enfileira o processamento assíncrono
        ProcessWebhookJob::dispatch($webhookLog->id)->onQueue('webhooks');

        return [
            'status_code' => 202,
            'body' => [
                'success' => true,
                'message' => 'Webhook recebido com sucesso e enfileirado para processamento.',
                'status' => 'ACCEPTED',
                'external_event_id' => $externalEventId,
                'webhook_id' => $webhookLog->uuid,
            ],
        ];
    }
}
