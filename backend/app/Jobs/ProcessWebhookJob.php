<?php

namespace App\Jobs;

use App\Enums\EventProcessingStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\WebhookLog;
use App\Services\Webhooks\EventNormalizer;
use App\Services\Webhooks\IdempotencyService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public int $webhookLogId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(EventNormalizer $normalizer, IdempotencyService $idempotency): void
    {
        $webhookLog = WebhookLog::withoutGlobalScopes()->find($this->webhookLogId);
        if (!$webhookLog) {
            return;
        }

        try {
            $payload = $webhookLog->payload;

            // 1. Normaliza o payload
            $normalized = $normalizer->normalize($payload);
            $externalEventId = $normalized['external_event_id'];
            $eventTypeKey = $normalized['event_type'];

            // 2. Verifica idempotência no banco
            $existingEvent = Event::withoutGlobalScopes()
                ->where('platform_id', $webhookLog->platform_id)
                ->where('external_event_id', $externalEventId)
                ->first();

            if ($existingEvent) {
                $webhookLog->update([
                    'event_id' => $existingEvent->id,
                    'processing_status' => EventProcessingStatus::DUPLICATE,
                    'processed_at' => Carbon::now('UTC'),
                ]);
                return;
            }

            // 3. Resolve ou cria o EventType
            $eventType = EventType::where('key', $eventTypeKey)
                ->where(function ($q) use ($webhookLog) {
                    $q->whereNull('platform_id')
                      ->orWhere('platform_id', $webhookLog->platform_id);
                })
                ->first();

            if (!$eventType) {
                $eventType = EventType::create([
                    'platform_id' => $webhookLog->platform_id,
                    'key' => $eventTypeKey,
                    'name' => str_replace('_', ' ', ucfirst(strtolower($eventTypeKey))),
                    'active' => true,
                ]);
            }

            // 4. Cria o registro do evento
            $event = Event::create([
                'platform_id' => $webhookLog->platform_id,
                'event_type_id' => $eventType->id,
                'external_event_id' => $externalEventId,
                'occurred_at' => $normalized['occurred_at'],
                'payload' => $payload,
                'normalized_payload' => $normalized['normalized_payload'],
                'processing_status' => EventProcessingStatus::QUEUED,
                'attempts' => 1,
            ]);

            // 5. Atualiza o WebhookLog com vínculo
            $webhookLog->update([
                'event_id' => $event->id,
                'external_event_id' => $externalEventId,
                'processing_status' => EventProcessingStatus::QUEUED,
                'processed_at' => Carbon::now('UTC'),
            ]);

            // 6. Enfileira o processamento de negócio do evento
            ProcessEventJob::dispatch($event->id)->onQueue('events');

        } catch (Throwable $e) {
            Log::error("Erro no ProcessWebhookJob #{$this->webhookLogId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $webhookLog->update([
                'processing_status' => EventProcessingStatus::FAILED,
                'error_message' => $e->getMessage(),
                'processed_at' => Carbon::now('UTC'),
            ]);

            throw $e;
        }
    }
}
