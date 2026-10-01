<?php

namespace App\Services\Analytics;

use App\Models\OperationalAlert;
use Carbon\Carbon;

class DashboardAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected PlayerAnalyticsService $playerService,
        protected FinancialAnalyticsService $financialService,
        protected BettingAnalyticsService $bettingService,
        protected MarketingAnalyticsService $marketingService,
        protected AutomationAnalyticsService $automationService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna o panorama executivo consolidado com todos os grupos de KPIs e séries temporais.
     */
    public function getDashboard(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'main_dashboard', $filters, 180, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $current = $resolved['current'];
            $prior = $resolved['prior'];
            $grouping = $resolved['grouping'];

            // 1. Jogadores
            $playerData = $this->playerService->getAnalytics($platformId, $filters);

            // 2. Financeiro
            $financialData = $this->financialService->getAnalytics($platformId, $filters);

            // 3. Apostas
            $bettingData = $this->bettingService->getAnalytics($platformId, $filters);

            // 4. Marketing
            $marketingData = $this->marketingService->getAnalytics($platformId, $filters);

            // 5. Automações
            $automationData = $this->automationService->getAnalytics($platformId, $filters);

            // 6. Alertas Críticos ou Warnings Ativos Recentes
            $recentAlerts = OperationalAlert::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->whereIn('status', ['TRIGGERED', 'ACKNOWLEDGED'])
                ->orderBy('triggered_at', 'desc')
                ->take(5)
                ->get(['id', 'severity', 'title', 'message', 'triggered_at', 'metric']);

            // 7. Gráfico Mestre Combinado de Evolução
            $combinedChart = $this->buildCombinedChart(
                $playerData['timeseries'],
                $financialData['timeseries'],
                $bettingData['timeseries']
            );

            return [
                'period' => [
                    'key' => $resolved['period_key'],
                    'current' => $current,
                    'prior' => $prior,
                    'grouping' => $grouping,
                ],
                'kpis' => [
                    'players' => $playerData['kpis'],
                    'financial' => $financialData['kpis'],
                    'betting' => $bettingData['kpis'],
                    'marketing' => $marketingData['kpis'],
                    'automations' => $automationData['kpis'],
                ],
                'charts' => [
                    'combined_evolution' => $combinedChart,
                    'player_timeseries' => $playerData['timeseries'],
                    'financial_timeseries' => $financialData['timeseries'],
                    'betting_timeseries' => $bettingData['timeseries'],
                ],
                'marketing_funnel' => $marketingData['funnel'],
                'automation_funnel' => $automationData['funnel'],
                'risk_distribution' => $playerData['risk_distribution'],
                'churn_metrics' => $playerData['churn_metrics'],
                'active_alerts' => $recentAlerts,
            ];
        });
    }

    protected function buildCombinedChart(array $playerTs, array $financialTs, array $bettingTs): array
    {
        $map = [];

        foreach ($playerTs as $pt) {
            $key = $pt['key'];
            $map[$key] = [
                'key' => $key,
                'label' => $pt['label'],
                'date' => $pt['date'],
                'new_players' => $pt['new_players'] ?? 0,
                'deposits' => 0.0,
                'withdrawals' => 0.0,
                'net_revenue' => 0.0,
                'bets' => 0,
                'bet_volume' => 0.0,
            ];
        }

        foreach ($financialTs as $ft) {
            $key = $ft['key'];
            if (!isset($map[$key])) {
                $map[$key] = [
                    'key' => $key,
                    'label' => $ft['label'],
                    'date' => $ft['date'],
                    'new_players' => 0,
                    'deposits' => 0.0,
                    'withdrawals' => 0.0,
                    'net_revenue' => 0.0,
                    'bets' => 0,
                    'bet_volume' => 0.0,
                ];
            }
            $map[$key]['deposits'] = $ft['deposits'] ?? 0.0;
            $map[$key]['withdrawals'] = $ft['withdrawals'] ?? 0.0;
            $map[$key]['net_revenue'] = $ft['net'] ?? 0.0;
        }

        foreach ($bettingTs as $bt) {
            $key = $bt['key'];
            if (isset($map[$key])) {
                $map[$key]['bets'] = $bt['bets'] ?? 0;
                $map[$key]['bet_volume'] = $bt['volume'] ?? 0.0;
            }
        }

        ksort($map);
        return array_values($map);
    }
}
