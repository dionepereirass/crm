<?php

namespace App\Services\Alerts;

use App\Models\AlertRule;
use App\Models\AutomationRun;
use App\Models\DataSubjectRequest;
use App\Models\Message;
use App\Models\OperationalAlert;
use App\Models\Player;
use App\Models\WebhookLog;
use App\Services\Privacy\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AlertRuleService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Avalia todas as regras de alerta ativas da plataforma contra as métricas reais do sistema.
     * Retorna a lista de novos alertas disparados.
     */
    public function evaluateRules(int $platformId): array
    {
        $rules = AlertRule::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->where('active', true)
            ->get();

        $triggeredAlerts = [];

        foreach ($rules as $rule) {
            $rule->update(['last_evaluated_at' => now()]);

            $currentValue = $this->resolveMetricValue($platformId, $rule->metric);
            if ($currentValue === null) {
                continue;
            }

            $isTriggered = $this->checkCondition($currentValue, $rule->operator, $rule->threshold);

            if ($isTriggered && !$rule->isInCooldown()) {
                $alert = OperationalAlert::create([
                    'platform_id' => $platformId,
                    'alert_rule_id' => $rule->id,
                    'metric' => $rule->metric,
                    'severity' => $rule->severity,
                    'status' => 'ACTIVE',
                    'title' => "Alerta: {$rule->name}",
                    'message' => "Métrica {$rule->metric} atingiu {$currentValue} (Regra: {$rule->operator} {$rule->threshold}).",
                    'current_value' => $currentValue,
                    'threshold_value' => $rule->threshold,
                    'triggered_at' => now(),
                    'metadata' => [
                        'rule_id' => $rule->id,
                        'operator' => $rule->operator,
                    ],
                ]);

                $rule->update(['last_triggered_at' => now()]);

                $this->auditService->log(
                    platformId: $platformId,
                    action: 'TRIGGER_ALERT',
                    resourceType: 'OperationalAlert',
                    resourceId: (string) $alert->id,
                    newValues: ['metric' => $rule->metric, 'current_value' => $currentValue, 'threshold' => $rule->threshold]
                );

                $triggeredAlerts[] = $alert;
            }
        }

        return $triggeredAlerts;
    }

    /**
     * Resolve o valor numérico em tempo real da métrica informada.
     */
    public function resolveMetricValue(int $platformId, string $metric): ?float
    {
        $now = Carbon::now();
        $oneHourAgo = $now->copy()->subHour();

        return match (strtoupper($metric)) {
            'PROVIDER_FAILURES' => (float) Message::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('status', 'FAILED')
                ->where('created_at', '>=', $oneHourAgo)
                ->count(),

            'DELIVERY_DROP' => (function () use ($platformId, $oneHourAgo) {
                $total = Message::withoutGlobalScopes()->where('platform_id', $platformId)->where('created_at', '>=', $oneHourAgo)->count();
                if ($total < 10) return 0.0;
                $delivered = Message::withoutGlobalScopes()->where('platform_id', $platformId)->where('status', 'DELIVERED')->where('created_at', '>=', $oneHourAgo)->count();
                return round((($total - $delivered) / $total) * 100, 1);
            })(),

            'INACTIVE_PLAYERS' => (float) Player::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('status', 'INACTIVE')
                ->count(),

            'AUTOMATION_FAILURES' => (float) AutomationRun::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('status', 'FAILED')
                ->where('created_at', '>=', $oneHourAgo)
                ->count(),

            'DSR_NEAR_SLA' => (float) DataSubjectRequest::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                ->where('due_at', '>', $now)
                ->where('due_at', '<=', $now->copy()->addHours(48))
                ->count(),

            'WEBHOOK_ERRORS' => (float) WebhookLog::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where(function ($q) {
                    $q->where('processing_status', 'FAILED')
                      ->orWhere('http_status', '>=', 400);
                })
                ->where('received_at', '>=', $oneHourAgo)
                ->count(),

            default => null,
        };
    }

    /**
     * Aplica o operador aritmético configurado.
     */
    public function checkCondition(float $current, string $operator, float $threshold): bool
    {
        return match (strtoupper($operator)) {
            'GT' => $current > $threshold,
            'GTE' => $current >= $threshold,
            'LT' => $current < $threshold,
            'LTE' => $current <= $threshold,
            'EQ' => abs($current - $threshold) < 0.0001,
            default => false,
        };
    }
}
