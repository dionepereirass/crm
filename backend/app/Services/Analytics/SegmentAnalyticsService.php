<?php

namespace App\Services\Analytics;

use App\Models\Campaign;
use App\Models\Segment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SegmentAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna métricas de utilização e audiência de segmentos dinâmicos.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'segments_overview', $filters, 600, function () use ($platformId) {
            $segments = Segment::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->get();

            $activeCount = $segments->where('is_active', true)->count();
            $totalCount = $segments->count();

            // Campanhas por segmento
            $campaignCounts = Campaign::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->whereNotNull('segment_id')
                ->select('segment_id', DB::raw('count(*) as count'))
                ->groupBy('segment_id')
                ->pluck('count', 'segment_id');

            $segmentList = $segments->map(function ($seg) use ($campaignCounts) {
                return [
                    'id' => $seg->id,
                    'name' => $seg->name,
                    'is_active' => (bool) $seg->is_active,
                    'cached_count' => (int) ($seg->cached_count ?? 0),
                    'campaigns_used' => (int) ($campaignCounts[$seg->id] ?? 0),
                    'last_calculated_at' => $seg->last_calculated_at,
                ];
            })->sortByDesc('cached_count')->values()->all();

            return [
                'total_segments' => $totalCount,
                'active_segments' => $activeCount,
                'segments' => $segmentList,
            ];
        });
    }
}
