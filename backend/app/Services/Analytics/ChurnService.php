<?php

namespace App\Services\Analytics;

use App\Models\Player;
use Carbon\Carbon;

class ChurnService
{
    /**
     * Calcula métricas de Churn com base no limite configurável de dias de inatividade (7, 14, 30, 60 dias).
     */
    public function getChurnMetrics(int $platformId, int $inactivityDays = 30): array
    {
        // Limita a valores seguros
        $inactivityDays = in_array($inactivityDays, [7, 14, 30, 60, 90]) ? $inactivityDays : 30;

        $now = Carbon::now();
        $churnCutoff = $now->copy()->subDays($inactivityDays);
        $warningCutoff = $now->copy()->subDays((int) ceil($inactivityDays / 2));

        $totalPlayers = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->count();

        if ($totalPlayers === 0) {
            return [
                'total_players' => 0,
                'churned_players' => 0,
                'at_risk_players' => 0,
                'active_players' => 0,
                'churn_rate' => 0.0,
                'inactivity_threshold_days' => $inactivityDays,
                'message' => 'Sem dados suficientes para cálculo de churn.',
            ];
        }

        // Churn: última atividade ou login anterior ao cutoff (ou nunca teve atividade e cadastro anterior ao cutoff)
        $churnedCount = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($churnCutoff) {
                $q->where(function ($sub) use ($churnCutoff) {
                    $sub->whereNotNull('last_activity_at')
                        ->where('last_activity_at', '<', $churnCutoff);
                })->orWhere(function ($sub) use ($churnCutoff) {
                    $sub->whereNull('last_activity_at')
                        ->whereNotNull('last_login_at')
                        ->where('last_login_at', '<', $churnCutoff);
                })->orWhere(function ($sub) use ($churnCutoff) {
                    $sub->whereNull('last_activity_at')
                        ->whereNull('last_login_at')
                        ->where('registered_at', '<', $churnCutoff);
                });
            })
            ->count();

        // Em Risco: última atividade entre warningCutoff e churnCutoff
        $atRiskCount = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($churnCutoff, $warningCutoff) {
                $q->where(function ($sub) use ($churnCutoff, $warningCutoff) {
                    $sub->where('last_activity_at', '>=', $churnCutoff)
                        ->where('last_activity_at', '<', $warningCutoff);
                })->orWhere(function ($sub) use ($churnCutoff, $warningCutoff) {
                    $sub->whereNull('last_activity_at')
                        ->where('last_login_at', '>=', $churnCutoff)
                        ->where('last_login_at', '<', $warningCutoff);
                });
            })
            ->count();

        $activeCount = max(0, $totalPlayers - $churnedCount);
        $churnRate = round(($churnedCount / $totalPlayers) * 100, 1);

        return [
            'total_players' => $totalPlayers,
            'churned_players' => $churnedCount,
            'at_risk_players' => $atRiskCount,
            'active_players' => $activeCount,
            'churn_rate' => $churnRate,
            'inactivity_threshold_days' => $inactivityDays,
        ];
    }
}
