<?php

namespace App\Jobs;

use App\Enums\EventProcessingStatus;
use App\Models\Event;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public int $eventId)
    {
        $this->onQueue('events');
    }

    public function handle(): void
    {
        $event = Event::withoutGlobalScopes()->with(['platform', 'eventType'])->find($this->eventId);
        if (!$event) {
            return;
        }

        // Se já foi processado ou marcado como duplicado, encerra
        if (in_array($event->processing_status, [EventProcessingStatus::PROCESSED, EventProcessingStatus::DUPLICATE])) {
            return;
        }

        $event->update([
            'processing_status' => EventProcessingStatus::PROCESSING,
            'attempts' => $event->attempts + 1,
        ]);

        try {
            $eventTypeKey = $event->eventType?->key ?? 'UNKNOWN';
            $externalPlayerId = $event->normalized_payload['player']['external_id'] ?? null;

            if ($externalPlayerId) {
                $player = Player::withoutGlobalScopes()
                    ->where('platform_id', $event->platform_id)
                    ->where('external_id', (string) $externalPlayerId)
                    ->first();

                if ($player) {
                    $event->player_id = $player->id;

                    // Efeitos colaterais no jogador
                    if ($eventTypeKey === 'PLAYER_UPDATED') {
                        $this->applyPlayerUpdates($player, $event->payload);
                    } elseif ($eventTypeKey === 'LOGIN') {
                        $player->update(['last_login_at' => $event->occurred_at]);
                    } elseif (in_array($eventTypeKey, ['DEPOSIT_SUCCESS', 'BET_PLACED'])) {
                        $player->update(['last_activity_at' => $event->occurred_at]);
                    }
                } else {
                    // Jogador não encontrado
                    if ($eventTypeKey === 'PLAYER_CREATED') {
                        // Criação explícita de jogador a partir de evento cadastral
                        $createdPlayer = $this->createPlayerFromEvent($event, $externalPlayerId);
                        $event->player_id = $createdPlayer->id;
                    } else {
                        // Não cria jogador silenciosamente para eventos transacionais
                        $event->update([
                            'processing_status' => EventProcessingStatus::PLAYER_NOT_FOUND,
                            'error_message' => "Jogador externo '{$externalPlayerId}' não encontrado na plataforma {$event->platform_id}.",
                            'processed_at' => Carbon::now('UTC'),
                        ]);
                        return;
                    }
                }
            }

            // Marca evento como concluído com sucesso
            $event->update([
                'processing_status' => EventProcessingStatus::PROCESSED,
                'error_message' => null,
                'processed_at' => Carbon::now('UTC'),
            ]);

            // Dispara avaliação assíncrona de automações (Fase 10)
            try {
                app(\App\Services\Automations\AutomationTriggerService::class)->handleEvent($event);
            } catch (Throwable $triggerEx) {
                Log::error("Erro ao avaliar automações para evento #{$event->id}: " . $triggerEx->getMessage());
            }

        } catch (Throwable $e) {
            Log::error("Erro no ProcessEventJob #{$this->eventId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $event->update([
                'processing_status' => EventProcessingStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function applyPlayerUpdates(Player $player, array $payload): void
    {
        $updates = [];
        if (!empty($payload['name'])) {
            $updates['name'] = $payload['name'];
        }
        if (!empty($payload['email'])) {
            $updates['email'] = $payload['email'];
        }
        if (!empty($payload['phone'])) {
            $updates['phone'] = $payload['phone'];
        }
        if (!empty($payload['state'])) {
            $updates['state'] = strtoupper($payload['state']);
        }
        if (!empty($payload['city'])) {
            $updates['city'] = $payload['city'];
        }
        if (!empty($payload['custom_fields']) && is_array($payload['custom_fields'])) {
            $updates['custom_fields'] = array_merge($player->custom_fields ?? [], $payload['custom_fields']);
        }

        if (!empty($updates)) {
            $player->update($updates);
        }
    }

    protected function createPlayerFromEvent(Event $event, string $externalPlayerId): Player
    {
        $payload = $event->payload;
        $playerData = $event->normalized_payload['player'] ?? [];

        $name = $playerData['name'] ?? $payload['name'] ?? "Jogador {$externalPlayerId}";
        $email = $playerData['email'] ?? $payload['email'] ?? "player_{$externalPlayerId}@operator.local";
        $phone = $playerData['phone'] ?? $payload['phone'] ?? null;

        return Player::create([
            'platform_id' => $event->platform_id,
            'external_id' => $externalPlayerId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'status' => 'active',
            'registered_at' => $event->occurred_at,
            'custom_fields' => $payload['custom_fields'] ?? [],
        ]);
    }
}
