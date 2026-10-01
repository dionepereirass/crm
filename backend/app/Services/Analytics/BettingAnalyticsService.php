<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\EventType;
use Carbon\Carbon;

class BettingAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna métricas analíticas completas de apostas esportivas e jogos.
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'betting_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $current = $resolved['current'];
            $prior = $resolved['prior'];
            $grouping = $resolved['grouping'];

            $curr = $this->calculateBettingStats($platformId, $current['start'], $current['end']);
            $prev = $this->calculateBettingStats($platformId, $prior['start'], $prior['end']);

            $kpis = [
                'total_bets' => [
                    'value' => $curr['total_bets'],
                    'prior_value' => $prev['total_bets'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['total_bets'], $prev['total_bets']),
                ],
                'turnover' => [
                    'value' => $curr['turnover'],
                    'prior_value' => $prev['turnover'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['turnover'], $prev['turnover']),
                    'formatted' => 'R$ ' . number_format($curr['turnover'], 2, ',', '.'),
                ],
                'average_bet_ticket' => [
                    'value' => $curr['average_ticket'],
                    'prior_value' => $prev['average_ticket'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['average_ticket'], $prev['average_ticket']),
                    'formatted' => 'R$ ' . number_format($curr['average_ticket'], 2, ',', '.'),
                ],
                'bets_won' => [
                    'value' => $curr['bets_won'],
                    'prior_value' => $prev['bets_won'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['bets_won'], $prev['bets_won']),
                ],
                'bets_lost' => [
                    'value' => $curr['bets_lost'],
                    'prior_value' => $prev['bets_lost'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['bets_lost'], $prev['bets_lost']),
                ],
                'win_rate' => [
                    'value' => $curr['win_rate'],
                    'prior_value' => $prev['win_rate'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['win_rate'] ?? 0, $prev['win_rate'] ?? 0),
                    'formatted' => $curr['win_rate'] !== null ? $curr['win_rate'] . '%' : null,
                ],
            ];

            $timeseries = $this->buildBettingTimeseries($platformId, $current['start'], $current['end'], $grouping);

            $hasData = $curr['total_bets'] > 0;

            return [
                'has_data' => $hasData,
                'message' => $hasData ? null : 'Sem dados suficientes de apostas para o período selecionado.',
                'period' => [
                    'key' => $resolved['period_key'],
                    'current' => $current,
                    'prior' => $prior,
                    'grouping' => $grouping,
                ],
                'kpis' => $kpis,
                'timeseries' => $timeseries,
            ];
        });
    }

    protected function calculateBettingStats(int $platformId, Carbon $start, Carbon $end): array
    {
        $betTypeIds = EventType::where(function ($q) {
            $q->whereIn('key', ['BET_PLACED', 'BET_SETTLED', 'BET']);
        })->pluck('id');

        $events = Event::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type_id', $betTypeIds)
            ->get(['normalized_payload', 'payload']);

        $totalBets = 0;
        $turnover = 0.0;
        $betsWon = 0;
        $betsLost = 0;

        foreach ($events as $ev) {
            $payload = $ev->normalized_payload ?? $ev->payload ?? [];
            $data = $payload['data'] ?? $payload;

            $amount = (float) ($data['amount'] ?? $data['bet_amount'] ?? 0.0);
            $status = strtoupper($data['status'] ?? $data['result'] ?? '');

            $totalBets++;
            $turnover += $amount;

            if (in_array($status, ['WON', 'WIN', 'GANHA', 'GANHOU'])) {
                $betsWon++;
            } elseif (in_array($status, ['LOST', 'LOSE', 'PERDIDA', 'PERDEU'])) {
                $betsLost++;
            }
        }

        $settledTotal = $betsWon + $betsLost;
        $winRate = $settledTotal > 0 ? round(($betsWon / $settledTotal) * 100, 1) : null;
        $avgTicket = $totalBets > 0 ? round($turnover / $totalBets, 2) : 0.0;

        return [
            'total_bets' => $totalBets,
            'turnover' => round($turnover, 2),
            'average_ticket' => $avgTicket,
            'bets_won' => $betsWon,
            'bets_lost' => $betsLost,
            'win_rate' => $winRate,
        ];
    }

    protected function buildBettingTimeseries(int $platformId, Carbon $start, Carbon $end, string $grouping): array
    {
        $slots = $this->periodService->generateDateSlots($start, $end, $grouping);

        $betTypeIds = EventType::where(function ($q) {
            $q->whereIn('key', ['BET_PLACED', 'BET_SETTLED', 'BET']);
        })->pluck('id');

        $events = Event::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type_id', $betTypeIds)
            ->get(['occurred_at', 'normalized_payload', 'payload']);

        foreach ($events as $ev) {
            $dt = Carbon::parse($ev->occurred_at);
            $key = match ($grouping) {
                'hour' => $dt->format('Y-m-d H:00'),
                'week' => $dt->format('Y-W'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };

            if (isset($slots[$key])) {
                $payload = $ev->normalized_payload ?? $ev->payload ?? [];
                $data = $payload['data'] ?? $payload;
                $amount = (float) ($data['amount'] ?? $data['bet_amount'] ?? 0.0);

                if (!isset($slots[$key]['bets'])) {
                    $slots[$key]['bets'] = 0;
                    $slots[$key]['volume'] = 0.0;
                }

                $slots[$key]['bets']++;
                $slots[$key]['volume'] += $amount;
            }
        }

        return array_values(array_map(function ($slot) {
            return [
                'key' => $slot['key'],
                'label' => $slot['label'],
                'date' => $slot['date'],
                'bets' => $slot['bets'] ?? 0,
                'volume' => round($slot['volume'] ?? 0.0, 2),
            ];
        }, $slots));
    }
}
