<?php

namespace App\Services\Analytics;

use App\Models\Message;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProviderAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna status de saúde operacional e métricas de desempenho de provedores.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'providers_overview', $filters, 180, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $start = $resolved['current']['start'];
            $end = $resolved['current']['end'];

            $providers = Provider::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->get();

            $results = [];

            foreach ($providers as $p) {
                // Checa Circuit Breaker no Redis
                $circuitOpen = false;
                try {
                    $circuitOpen = (bool) Redis::get("betcrm:circuit_breaker:provider:{$p->id}:open");
                } catch (\Throwable $e) {}

                $health = 'ACTIVE';
                if (!$p->is_active) {
                    $health = 'INACTIVE';
                } elseif ($circuitOpen) {
                    $health = 'CIRCUIT_OPEN';
                }

                $msgQuery = Message::withoutGlobalScopes()
                    ->where('platform_id', $platformId)
                    ->where('provider_id', $p->id)
                    ->whereBetween('created_at', [$start, $end]);

                $total = (clone $msgQuery)->count();
                $success = (clone $msgQuery)->whereIn('status', ['SENT', 'DELIVERED'])->count();
                $failed = (clone $msgQuery)->where('status', 'FAILED')->count();

                if ($health === 'ACTIVE' && $total > 10 && ($failed / $total) > 0.5) {
                    $health = 'ERROR';
                }

                $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 100.0;

                $results[] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'driver' => $p->driver,
                    'channel' => $p->channel,
                    'health' => $health,
                    'circuit_open' => $circuitOpen,
                    'total_messages' => $total,
                    'success_messages' => $success,
                    'failed_messages' => $failed,
                    'success_rate' => $successRate,
                    'priority' => $p->priority ?? 1,
                ];
            }

            return [
                'period' => $resolved['current'],
                'providers' => $results,
                'total_providers' => count($providers),
            ];
        });
    }
}
