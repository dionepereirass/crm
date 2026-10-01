<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlayerAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected PlayerRiskService $riskService,
        protected ChurnService $churnService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna visão analítica completa dos jogadores (KPIs, evolução temporal, risco e churn).
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'players_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $current = $resolved['current'];
            $prior = $resolved['prior'];
            $grouping = $resolved['grouping'];

            // 1. KPIs do Período Atual
            $currentStats = $this->calculatePeriodStats($platformId, $current['start'], $current['end']);

            // 2. KPIs do Período Anterior
            $priorStats = $this->calculatePeriodStats($platformId, $prior['start'], $prior['end']);

            // 3. Comparações percentuais
            $kpis = [
                'total_players' => [
                    'value' => $currentStats['total_players'],
                    'prior_value' => $priorStats['total_players'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($currentStats['total_players'], $priorStats['total_players']),
                ],
                'new_players' => [
                    'value' => $currentStats['new_players'],
                    'prior_value' => $priorStats['new_players'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($currentStats['new_players'], $priorStats['new_players']),
                ],
                'active_players' => [
                    'value' => $currentStats['active_players'],
                    'prior_value' => $priorStats['active_players'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($currentStats['active_players'], $priorStats['active_players']),
                ],
                'inactive_players' => [
                    'value' => $currentStats['inactive_players'],
                    'prior_value' => $priorStats['inactive_players'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($currentStats['inactive_players'], $priorStats['inactive_players']),
                ],
                'reactivated_players' => [
                    'value' => $currentStats['reactivated_players'],
                    'prior_value' => $priorStats['reactivated_players'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($currentStats['reactivated_players'], $priorStats['reactivated_players']),
                ],
            ];

            // 4. Série Temporal (Evolução Novos x Ativos)
            $timeseries = $this->buildPlayerTimeseries($platformId, $current['start'], $current['end'], $grouping);

            // 5. Distribuição de Risco
            $riskDistribution = $this->riskService->getRiskDistribution($platformId);

            // 6. Churn
            $inactivityDays = isset($filters['inactivity_days']) ? (int) $filters['inactivity_days'] : 30;
            $churnMetrics = $this->churnService->getChurnMetrics($platformId, $inactivityDays);

            return [
                'period' => [
                    'key' => $resolved['period_key'],
                    'current' => $current,
                    'prior' => $prior,
                    'grouping' => $grouping,
                ],
                'kpis' => $kpis,
                'timeseries' => $timeseries,
                'risk_distribution' => $riskDistribution,
                'churn_metrics' => $churnMetrics,
            ];
        });
    }

    /**
     * Calcula métricas agregadas para um intervalo determinado.
     */
    protected function calculatePeriodStats(int $platformId, Carbon $start, Carbon $end): array
    {
        $baseQuery = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at');

        // Total histórico acumulado até o fim do período
        $totalPlayers = (clone $baseQuery)->where('created_at', '<=', $end)->count();

        // Novos cadastros no intervalo
        $newPlayers = (clone $baseQuery)->whereBetween('created_at', [$start, $end])->count();

        // Jogadores ativos no intervalo (com atividade ou login no período)
        $activePlayers = (clone $baseQuery)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('last_activity_at', [$start, $end])
                  ->orWhereBetween('last_login_at', [$start, $end]);
            })
            ->count();

        // Inativos: total menos os ativos
        $inactivePlayers = max(0, $totalPlayers - $activePlayers);

        // Reativados: cadastrados antes do período, sem login nos 30 dias anteriores a $start, mas ativos durante [$start, $end]
        $thirtyDaysBeforeStart = $start->copy()->subDays(30);
        $reactivatedPlayers = (clone $baseQuery)
            ->where('created_at', '<', $start)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('last_activity_at', [$start, $end])
                  ->orWhereBetween('last_login_at', [$start, $end]);
            })
            ->where(function ($q) use ($start, $thirtyDaysBeforeStart) {
                $q->where('last_activity_at', '<', $thirtyDaysBeforeStart)
                  ->orWhereNull('last_activity_at');
            })
            ->count();

        return [
            'total_players' => $totalPlayers,
            'new_players' => $newPlayers,
            'active_players' => $activePlayers,
            'inactive_players' => $inactivePlayers,
            'reactivated_players' => $reactivatedPlayers,
        ];
    }

    /**
     * Constrói a série temporal agrupada por hora/dia/semana/mês.
     */
    protected function buildPlayerTimeseries(int $platformId, Carbon $start, Carbon $end, string $grouping): array
    {
        $slots = $this->periodService->generateDateSlots($start, $end, $grouping);

        $players = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->select('created_at')
            ->get();

        foreach ($players as $p) {
            $dt = Carbon::parse($p->created_at);
            $key = match ($grouping) {
                'hour' => $dt->format('Y-m-d H:00'),
                'week' => $dt->format('Y-W'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };

            if (isset($slots[$key])) {
                $slots[$key]['new_players'] = ($slots[$key]['new_players'] ?? 0) + 1;
            }
        }

        // Normaliza valores
        return array_values(array_map(function ($slot) {
            return [
                'key' => $slot['key'],
                'label' => $slot['label'],
                'date' => $slot['date'],
                'new_players' => $slot['new_players'] ?? 0,
            ];
        }, $slots));
    }

    /**
     * Análise real de Cohort (Art. 7) baseada na data de cadastro dos jogadores e atividade subsequente.
     * Retorna a matriz de retenção D1, D7, D14, D30, D60, D90.
     */
    public function getCohortAnalysis(int $platformId, int $cohortWeeks = 6): array
    {
        return $this->cacheService->remember($platformId, 'cohort_analysis', ['weeks' => $cohortWeeks], 600, function () use ($platformId, $cohortWeeks) {
            $now = Carbon::now();
            $cohorts = [];

            for ($i = $cohortWeeks - 1; $i >= 0; $i--) {
                $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $cohortLabel = $weekStart->format('d/m') . ' - ' . $weekEnd->format('d/m');

                // Jogadores cadastrados nesta semana
                $cohortPlayers = Player::withoutGlobalScopes()
                    ->where('platform_id', $platformId)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->get(['id', 'created_at', 'last_activity_at', 'last_login_at']);

                $cohortSize = $cohortPlayers->count();
                if ($cohortSize === 0) {
                    $cohorts[] = [
                        'cohort' => $cohortLabel,
                        'size' => 0,
                        'd1' => null,
                        'd7' => null,
                        'd14' => null,
                        'd30' => null,
                        'd60' => null,
                        'd90' => null,
                    ];
                    continue;
                }

                $retentionDays = [1 => 'd1', 7 => 'd7', 14 => 'd14', 30 => 'd30', 60 => 'd60', 90 => 'd90'];
                $cohortData = [
                    'cohort' => $cohortLabel,
                    'size' => $cohortSize,
                ];

                foreach ($retentionDays as $days => $col) {
                    // Se o tempo transcorrido da coorte ainda não atingiu X dias
                    if ($weekEnd->copy()->addDays($days)->isFuture()) {
                        $cohortData[$col] = null;
                        continue;
                    }

                    $retained = 0;
                    foreach ($cohortPlayers as $p) {
                        $regDate = Carbon::parse($p->created_at);
                        $targetDate = $regDate->copy()->addDays($days);
                        $lastAct = $p->last_activity_at ? Carbon::parse($p->last_activity_at) : ($p->last_login_at ? Carbon::parse($p->last_login_at) : null);

                        if ($lastAct && $lastAct->gte($targetDate->copy()->subDay())) {
                            $retained++;
                        }
                    }

                    $cohortData[$col] = round(($retained / $cohortSize) * 100, 1);
                }

                $cohorts[] = $cohortData;
            }

            return [
                'cohorts' => $cohorts,
                'metrics' => ['D1', 'D7', 'D14', 'D30', 'D60', 'D90'],
            ];
        });
    }

    /**
     * Retorna a curva agregada de retenção de jogadores (D1 a D90).
     */
    public function getRetentionCurve(int $platformId): array
    {
        $cohort = $this->getCohortAnalysis($platformId, 8);
        $cohorts = $cohort['cohorts'];

        $averages = [];
        foreach (['d1' => 'D1', 'd7' => 'D7', 'd14' => 'D14', 'd30' => 'D30', 'd60' => 'D60', 'd90' => 'D90'] as $key => $label) {
            $valid = array_filter(array_column($cohorts, $key), fn($v) => $v !== null);
            $avg = count($valid) > 0 ? round(array_sum($valid) / count($valid), 1) : null;
            $averages[] = [
                'day' => $label,
                'retention_rate' => $avg,
            ];
        }

        return [
            'curve' => $averages,
            'cohort_table' => $cohorts,
        ];
    }
}
