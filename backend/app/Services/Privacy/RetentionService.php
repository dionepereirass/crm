<?php

namespace App\Services\Privacy;

use App\Enums\RetentionAction;
use App\Models\AuditLog;
use App\Models\MessageEvent;
use App\Models\Player;
use App\Models\RetentionPolicy;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class RetentionService
{
    public function __construct(
        protected AuditService $auditService,
        protected PlayerAnonymizationService $anonymizationService
    ) {}

    /**
     * Processa todas as políticas de retenção ativas de uma plataforma em lotes (batch/chunk).
     */
    public function processPolicies(int $platformId): array
    {
        $policies = RetentionPolicy::where('platform_id', $platformId)
            ->where('active', true)
            ->get();

        $summary = [
            'platform_id' => $platformId,
            'policies_processed' => 0,
            'records_deleted' => 0,
            'records_anonymized' => 0,
            'details' => [],
        ];

        foreach ($policies as $policy) {
            $cutoffDate = now()->subDays($policy->retention_days);
            $action = $policy->action instanceof RetentionAction ? $policy->action : RetentionAction::tryFrom($policy->action) ?? RetentionAction::RETAIN;

            if ($action === RetentionAction::RETAIN) {
                $summary['details'][$policy->data_category] = ['action' => 'RETAIN', 'affected' => 0];
                continue;
            }

            $affectedCount = 0;

            switch (strtoupper($policy->data_category)) {
                case 'LOGS':
                case 'WEBHOOK_LOGS':
                    if ($action === RetentionAction::DELETE) {
                        $affectedCount = WebhookLog::where('platform_id', $platformId)
                            ->where(function ($q) use ($cutoffDate) {
                                $q->where('received_at', '<', $cutoffDate)
                                  ->orWhere('created_at', '<', $cutoffDate);
                            })
                            ->delete();
                        $summary['records_deleted'] += $affectedCount;
                    }
                    break;

                case 'TRACKING_EVENTS':
                    if ($action === RetentionAction::DELETE) {
                        // Deleta eventos de tracking de mensagens antigos em chunks
                        $query = MessageEvent::where('platform_id', $platformId)
                            ->where('created_at', '<', $cutoffDate);
                        $affectedCount = $query->count();
                        $query->delete();
                        $summary['records_deleted'] += $affectedCount;
                    }
                    break;

                case 'INACTIVE_PLAYERS':
                    if ($action === RetentionAction::ANONYMIZE) {
                        // Anonimiza jogadores inativos em lotes de 100
                        Player::where('platform_id', $platformId)
                            ->where('status', '!=', 'ANONYMIZED')
                            ->where(function ($q) use ($cutoffDate) {
                                $q->where('last_activity_at', '<', $cutoffDate)
                                  ->orWhere(function ($sub) use ($cutoffDate) {
                                      $sub->whereNull('last_activity_at')
                                          ->where('created_at', '<', $cutoffDate);
                                  });
                            })
                            ->chunkById(100, function ($players) use (&$affectedCount) {
                                foreach ($players as $player) {
                                    $this->anonymizationService->anonymize(
                                        $player,
                                        true,
                                        null,
                                        'Execução automática de política de retenção por inatividade'
                                    );
                                    $affectedCount++;
                                }
                            });
                        $summary['records_anonymized'] += $affectedCount;
                    }
                    break;

                default:
                    Log::info("RetentionService: Categoria '{$policy->data_category}' sem handler específico configurado.");
                    break;
            }

            $summary['policies_processed']++;
            $summary['details'][$policy->data_category] = [
                'action' => $action->value,
                'affected' => $affectedCount,
            ];
        }

        // Registra auditoria da execução de retenção
        $this->auditService->log(
            $platformId,
            'RETENTION_POLICIES_EXECUTED',
            'RetentionPolicy',
            null,
            null,
            $summary,
            'SYSTEM'
        );

        return $summary;
    }
}
