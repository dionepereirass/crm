<?php

namespace App\Services\Analytics;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AutomationAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna visão analítica de fluxos de automação e jornadas.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'automations_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $start = $resolved['current']['start'];
            $end = $resolved['current']['end'];

            $activeAutomations = Automation::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('status', 'ACTIVE')
                ->count();

            $totalAutomations = Automation::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->count();

            $runsQuery = AutomationRun::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->whereBetween('created_at', [$start, $end]);

            $totalRuns = (clone $runsQuery)->count();
            $completedRuns = (clone $runsQuery)->where('status', 'COMPLETED')->count();
            $failedRuns = (clone $runsQuery)->where('status', 'FAILED')->count();
            $cancelledRuns = (clone $runsQuery)->where('status', 'CANCELLED')->count();
            $waitingRuns = (clone $runsQuery)->where('status', 'WAITING')->count();

            // Total de passos executados
            $runIds = (clone $runsQuery)->pluck('id');
            $stepsExecuted = AutomationStep::whereIn('automation_run_id', $runIds)->count();
            $actionStepsExecuted = AutomationStep::whereIn('automation_run_id', $runIds)
                ->whereHas('node', fn($q) => $q->where('node_type', 'ACTION'))
                ->count();

            $successRate = $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 100.0;

            // Funil de Automação
            $funnel = [
                ['stage' => 'TRIGGER', 'count' => $totalRuns, 'percentage' => 100.0],
                ['stage' => 'RUN', 'count' => $totalRuns, 'percentage' => 100.0],
                ['stage' => 'STEPS', 'count' => $stepsExecuted, 'percentage' => $totalRuns > 0 ? round(($stepsExecuted / ($totalRuns * 2 ?: 1)) * 100, 1) : 0.0],
                ['stage' => 'ACTION', 'count' => $actionStepsExecuted, 'percentage' => $stepsExecuted > 0 ? round(($actionStepsExecuted / $stepsExecuted) * 100, 1) : 0.0],
                ['stage' => 'CONVERSION', 'count' => $completedRuns, 'percentage' => $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 0.0],
            ];

            return [
                'period' => $resolved['current'],
                'kpis' => [
                    'active_automations' => $activeAutomations,
                    'total_automations' => $totalAutomations,
                    'total_runs' => $totalRuns,
                    'completed_runs' => $completedRuns,
                    'failed_runs' => $failedRuns,
                    'cancelled_runs' => $cancelledRuns,
                    'waiting_runs' => $waitingRuns,
                    'steps_executed' => $stepsExecuted,
                    'success_rate' => $successRate,
                ],
                'funnel' => $funnel,
            ];
        });
    }
}
