<?php

namespace App\Services\Automations;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTriggerType;
use App\Jobs\StartAutomationRunJob;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\Event;
use App\Models\MessageEvent;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class AutomationTriggerService
{
    /**
     * Processa um Evento transacional ou comportamental e avalia disparos de automações.
     */
    public function handleEvent(Event $event): int
    {
        $platformId = $event->platform_id;
        $playerId = $event->player_id;
        $eventTypeKey = $event->eventType?->key ?? 'UNKNOWN';

        if (!$playerId) {
            return 0;
        }

        $triggerType = AutomationTriggerType::tryFrom($eventTypeKey);
        if (!$triggerType) {
            return 0;
        }

        return $this->dispatchForTrigger(
            $platformId,
            $playerId,
            $triggerType->value,
            (string) $event->id,
            $event->normalized_payload ?: $event->payload ?: []
        );
    }

    /**
     * Processa um Evento de Mensagem/Campanha (DELIVERED, OPENED, CLICKED).
     */
    public function handleMessageEvent(MessageEvent $messageEvent): int
    {
        $message = $messageEvent->message;
        if (!$message || !$message->player_id) {
            return 0;
        }

        $eventType = strtoupper($messageEvent->event_type);
        $triggerType = match ($eventType) {
            'DELIVERED' => AutomationTriggerType::CAMPAIGN_DELIVERED->value,
            'OPENED' => AutomationTriggerType::CAMPAIGN_OPENED->value,
            'CLICKED' => AutomationTriggerType::CAMPAIGN_CLICKED->value,
            default => null,
        };

        if (!$triggerType) {
            return 0;
        }

        $metadata = [
            'message_id' => $message->id,
            'campaign_id' => $message->campaign_id,
            'channel' => $message->channel,
            'event_type' => $eventType,
        ];

        return $this->dispatchForTrigger(
            $message->platform_id,
            $message->player_id,
            $triggerType,
            "msg-evt-{$messageEvent->id}",
            $metadata
        );
    }

    /**
     * Processa um gatilho direto do jogador (ex: TAG_ADDED, TAG_REMOVED, SEGMENT_ENTERED, SEGMENT_EXITED).
     */
    public function handlePlayerTrigger(Player $player, string $triggerType, ?string $referenceId = null, array $metadata = []): int
    {
        return $this->dispatchForTrigger(
            $player->platform_id,
            $player->id,
            $triggerType,
            $referenceId ?: (string) now()->timestamp,
            $metadata
        );
    }

    /**
     * Avalia automações ativas, regras de reentrada, cooldown e cria execuções assíncronas.
     */
    public function dispatchForTrigger(
        int $platformId,
        int $playerId,
        string $triggerType,
        string $eventId,
        array $eventPayload = []
    ): int {
        $automations = Automation::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->where('status', AutomationStatus::ACTIVE)
            ->where('trigger_type', $triggerType)
            ->get();

        if ($automations->isEmpty()) {
            return 0;
        }

        $player = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->find($playerId);

        if (!$player || $player->status === 'BLOCKED') {
            return 0;
        }

        $dispatchedCount = 0;

        foreach ($automations as $automation) {
            try {
                if ($this->shouldTriggerAutomation($automation, $player, $eventId)) {
                    $run = $this->createRun($automation, $player, $eventId, $eventPayload);
                    if ($run) {
                        StartAutomationRunJob::dispatch($run->id)->onQueue('automations');
                        $dispatchedCount++;
                    }
                }
            } catch (Throwable $e) {
                Log::error("Erro ao avaliar automação #{$automation->id} para jogador #{$player->id}: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        return $dispatchedCount;
    }

    /**
     * Verifica elegibilidade de reentrada e cooldown do jogador para a automação.
     */
    public function shouldTriggerAutomation(Automation $automation, Player $player, string $eventId): bool
    {
        $settings = $automation->settings ?? [];
        $reentryPolicy = $settings['reentry_policy'] ?? 'BLOCK_REENTRY';
        $reentryDays = (int) ($settings['reentry_after_days'] ?? 30);
        $cooldownDays = (int) ($settings['cooldown_days'] ?? 0);

        // 1. Verificação de Cooldown Geral
        if ($cooldownDays > 0) {
            $recentRun = AutomationRun::where('automation_id', $automation->id)
                ->where('player_id', $player->id)
                ->where('created_at', '>=', Carbon::now()->subDays($cooldownDays))
                ->exists();

            if ($recentRun) {
                return false;
            }
        }

        // 2. Política de Reentrada
        if ($reentryPolicy === 'BLOCK_REENTRY') {
            // Não permite nova entrada enquanto houver execução em andamento (RUNNING ou WAITING)
            $activeRun = AutomationRun::where('automation_id', $automation->id)
                ->where('player_id', $player->id)
                ->whereIn('status', [AutomationRunStatus::RUNNING, AutomationRunStatus::WAITING])
                ->exists();

            if ($activeRun) {
                return false;
            }
        } elseif ($reentryPolicy === 'REENTRY_AFTER') {
            // Permite nova entrada apenas se a última execução foi anterior ao período configurado
            $recentExecution = AutomationRun::where('automation_id', $automation->id)
                ->where('player_id', $player->id)
                ->where('created_at', '>=', Carbon::now()->subDays($reentryDays))
                ->exists();

            if ($recentExecution) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cria atomicamente o registro de execução com garantia de idempotência no Redis e PostgreSQL.
     */
    protected function createRun(Automation $automation, Player $player, string $eventId, array $eventPayload): ?AutomationRun
    {
        $platformId = $automation->platform_id;
        $automationId = $automation->id;
        $playerId = $player->id;

        // Chave de idempotência determinística
        $idempotencyKey = hash('sha256', "auto:{$platformId}:{$automationId}:{$playerId}:{$eventId}");

        // 1. Bloqueio no Redis contra rajadas simultâneas
        $redisLockKey = "betcrm:automation:idempotency:{$automationId}:{$idempotencyKey}";
        try {
            $acquired = (bool) Redis::set($redisLockKey, 1, 'EX', 60, 'NX');
            if (!$acquired) {
                return null;
            }
        } catch (\Throwable) {}

        return DB::transaction(function () use ($automation, $player, $idempotencyKey, $eventId, $eventPayload) {
            // 2. Checagem definitiva no Banco de Dados
            $existing = AutomationRun::where('automation_id', $automation->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return null;
            }

            $run = AutomationRun::create([
                'automation_id' => $automation->id,
                'platform_id' => $automation->platform_id,
                'player_id' => $player->id,
                'status' => AutomationRunStatus::RUNNING,
                'current_node_id' => null,
                'idempotency_key' => $idempotencyKey,
                'started_at' => now(),
                'metadata' => [
                    'event_id' => $eventId,
                    'event_payload' => $eventPayload,
                ],
            ]);

            AutomationLog::log(
                $automation->id,
                'RUN_STARTED',
                "Execução #{$run->id} iniciada para jogador #{$player->id} a partir do evento '{$automation->trigger_type->value}'.",
                'INFO',
                $run->id,
                null,
                ['event_id' => $eventId]
            );

            return $run;
        });
    }
}
