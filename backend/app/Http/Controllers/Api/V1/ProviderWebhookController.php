<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\Tracking\MessageEventService;
use App\Services\Tracking\ProviderEventNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderWebhookController extends Controller
{
    public function __construct(
        protected ProviderEventNormalizer $normalizer,
        protected MessageEventService $eventService
    ) {}

    /**
     * Recebe callbacks e webhooks de status de entrega de provedores externos (Brevo, Zenvia, etc.).
     */
    public function handle(Request $request, string $driver): JsonResponse
    {
        $normalizedDriver = strtolower($driver);
        $payload = $request->all();
        $headers = $request->headers->all();

        // 1. Validação de autenticidade / token de webhook (quando configurado no cabeçalho ou query)
        $webhookToken = $request->header('X-Webhook-Token') ?? $request->query('token');
        if ($webhookToken && !empty(config("services.providers.{$normalizedDriver}.webhook_secret"))) {
            $expected = config("services.providers.{$normalizedDriver}.webhook_secret");
            if (!hash_equals($expected, $webhookToken)) {
                return response()->json(['error' => 'Assinatura ou token de webhook inválido.'], 403);
            }
        }

        // 2. Resolve o provedor no banco se possível
        $provider = Provider::where('driver', $normalizedDriver)
            ->where('status', 'ACTIVE')
            ->first();

        // 3. Suporte a lotes de eventos (array de eventos) ou evento único
        $eventsList = isset($payload[0]) && is_array($payload[0]) ? $payload : [$payload];
        $processedCount = 0;

        // Idempotência estrita rápida para evento individual
        if (count($eventsList) === 1) {
            $normalizedData = $this->normalizer->normalize($normalizedDriver, $eventsList[0], $headers);
            $providerEventId = $normalizedData['provider_event_id'] ?? null;
            if ($providerEventId && \App\Models\MessageEvent::where('provider_event_id', (string) $providerEventId)->exists()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Evento já processado anteriormente (idempotente).',
                    'events_processed' => 0,
                ], 200);
            }
        }

        foreach ($eventsList as $singlePayload) {
            $normalizedData = $this->normalizer->normalize($normalizedDriver, $singlePayload, $headers);
            $event = $this->eventService->handleNormalizedEvent($normalizedData, $provider?->id);
            if ($event) {
                $processedCount++;
            }
        }

        Log::info("[PROVIDER WEBHOOK] Webhook recebido de {$normalizedDriver}", [
            'driver' => $normalizedDriver,
            'events_count' => count($eventsList),
            'processed' => $processedCount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processado com sucesso.',
            'events_processed' => $processedCount,
        ], 200);
    }
}
