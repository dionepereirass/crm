<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\EventType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialAnalyticsService
{
    public function __construct(
        protected PeriodService $periodService,
        protected AnalyticsCacheService $cacheService
    ) {}

    /**
     * Retorna métricas financeiras completas da plataforma (KPIs, FTDs, evolução e comparativos).
     */
    public function getAnalytics(int $platformId, array $filters = []): array
    {
        return $this->cacheService->remember($platformId, 'financial_overview', $filters, 300, function () use ($platformId, $filters) {
            $resolved = $this->periodService->resolvePeriod($filters);
            $current = $resolved['current'];
            $prior = $resolved['prior'];
            $grouping = $resolved['grouping'];

            // 1. Métricas do Período Atual
            $curr = $this->calculateFinancialStats($platformId, $current['start'], $current['end']);

            // 2. Métricas do Período Anterior
            $prev = $this->calculateFinancialStats($platformId, $prior['start'], $prior['end']);

            // 3. Comparações percentuais
            $kpis = [
                'total_deposit_amount' => [
                    'value' => $curr['deposit_amount'],
                    'prior_value' => $prev['deposit_amount'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['deposit_amount'], $prev['deposit_amount']),
                    'formatted' => 'R$ ' . number_format($curr['deposit_amount'], 2, ',', '.'),
                ],
                'deposit_count' => [
                    'value' => $curr['deposit_count'],
                    'prior_value' => $prev['deposit_count'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['deposit_count'], $prev['deposit_count']),
                ],
                'average_deposit_ticket' => [
                    'value' => $curr['deposit_ticket'],
                    'prior_value' => $prev['deposit_ticket'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['deposit_ticket'], $prev['deposit_ticket']),
                    'formatted' => 'R$ ' . number_format($curr['deposit_ticket'], 2, ',', '.'),
                ],
                'total_withdrawal_amount' => [
                    'value' => $curr['withdrawal_amount'],
                    'prior_value' => $prev['withdrawal_amount'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['withdrawal_amount'], $prev['withdrawal_amount']),
                    'formatted' => 'R$ ' . number_format($curr['withdrawal_amount'], 2, ',', '.'),
                ],
                'withdrawal_count' => [
                    'value' => $curr['withdrawal_count'],
                    'prior_value' => $prev['withdrawal_count'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['withdrawal_count'], $prev['withdrawal_count']),
                ],
                'average_withdrawal_ticket' => [
                    'value' => $curr['withdrawal_ticket'],
                    'prior_value' => $prev['withdrawal_ticket'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['withdrawal_ticket'], $prev['withdrawal_ticket']),
                    'formatted' => 'R$ ' . number_format($curr['withdrawal_ticket'], 2, ',', '.'),
                ],
                'net_revenue' => [
                    'value' => $curr['net_amount'],
                    'prior_value' => $prev['net_amount'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['net_amount'], $prev['net_amount']),
                    'formatted' => 'R$ ' . number_format($curr['net_amount'], 2, ',', '.'),
                ],
                'unique_depositors' => [
                    'value' => $curr['unique_depositors'],
                    'prior_value' => $prev['unique_depositors'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['unique_depositors'], $prev['unique_depositors']),
                ],
                'first_time_depositors' => [
                    'value' => $curr['first_time_depositors'],
                    'prior_value' => $prev['first_time_depositors'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['first_time_depositors'], $prev['first_time_depositors']),
                ],
                'recurring_depositors' => [
                    'value' => $curr['recurring_depositors'],
                    'prior_value' => $prev['recurring_depositors'],
                    'change_percentage' => $this->periodService->calculatePercentageChange($curr['recurring_depositors'], $prev['recurring_depositors']),
                ],
            ];

            // 4. Série Temporal da Evolução Financeira
            $timeseries = $this->buildFinancialTimeseries($platformId, $current['start'], $current['end'], $grouping);

            return [
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

    /**
     * Calcula métricas agregadas a partir dos eventos normalizados da tabela events.
     */
    protected function calculateFinancialStats(int $platformId, Carbon $start, Carbon $end): array
    {
        $depositTypeIds = EventType::where(function ($q) {
            $q->where('key', 'DEPOSIT_SUCCESS')->orWhere('key', 'DEPOSIT');
        })->pluck('id');

        $withdrawalTypeIds = EventType::where(function ($q) {
            $q->where('key', 'WITHDRAWAL_SUCCESS')->orWhere('key', 'WITHDRAWAL');
        })->pluck('id');

        $events = Event::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type_id', $depositTypeIds->merge($withdrawalTypeIds))
            ->get(['event_type_id', 'player_id', 'normalized_payload', 'payload']);

        $depositAmount = 0.0;
        $depositCount = 0;
        $withdrawalAmount = 0.0;
        $withdrawalCount = 0;
        $depositorPlayerIds = [];

        foreach ($events as $ev) {
            $amount = $this->extractAmount($ev);
            if ($depositTypeIds->contains($ev->event_type_id)) {
                $depositAmount += $amount;
                $depositCount++;
                if ($ev->player_id) {
                    $depositorPlayerIds[$ev->player_id] = true;
                }
            } elseif ($withdrawalTypeIds->contains($ev->event_type_id)) {
                $withdrawalAmount += $amount;
                $withdrawalCount++;
            }
        }

        $uniqueDepositorsCount = count($depositorPlayerIds);
        $depositTicket = $depositCount > 0 ? round($depositAmount / $depositCount, 2) : 0.0;
        $withdrawalTicket = $withdrawalCount > 0 ? round($withdrawalAmount / $withdrawalCount, 2) : 0.0;
        $netAmount = round($depositAmount - $withdrawalAmount, 2);

        // FTDs (First Time Depositors) vs Recorrentes
        $ftdCount = 0;
        if (!empty($depositorPlayerIds)) {
            $playerIds = array_keys($depositorPlayerIds);
            // Jogadores que já possuíam depósito antes de $start
            $priorDepositors = Event::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->whereIn('player_id', $playerIds)
                ->whereIn('event_type_id', $depositTypeIds)
                ->where('occurred_at', '<', $start)
                ->distinct('player_id')
                ->pluck('player_id')
                ->all();

            $ftdCount = count(array_diff($playerIds, $priorDepositors));
        }

        $recurringDepositors = max(0, $uniqueDepositorsCount - $ftdCount);

        return [
            'deposit_amount' => round($depositAmount, 2),
            'deposit_count' => $depositCount,
            'deposit_ticket' => $depositTicket,
            'withdrawal_amount' => round($withdrawalAmount, 2),
            'withdrawal_count' => $withdrawalCount,
            'withdrawal_ticket' => $withdrawalTicket,
            'net_amount' => $netAmount,
            'unique_depositors' => $uniqueDepositorsCount,
            'first_time_depositors' => $ftdCount,
            'recurring_depositors' => $recurringDepositors,
        ];
    }

    /**
     * Monta os pontos da série temporal financeira.
     */
    protected function buildFinancialTimeseries(int $platformId, Carbon $start, Carbon $end, string $grouping): array
    {
        $slots = $this->periodService->generateDateSlots($start, $end, $grouping);

        $depositTypeIds = EventType::where(function ($q) {
            $q->where('key', 'DEPOSIT_SUCCESS')->orWhere('key', 'DEPOSIT');
        })->pluck('id');

        $withdrawalTypeIds = EventType::where(function ($q) {
            $q->where('key', 'WITHDRAWAL_SUCCESS')->orWhere('key', 'WITHDRAWAL');
        })->pluck('id');

        $events = Event::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type_id', $depositTypeIds->merge($withdrawalTypeIds))
            ->get(['event_type_id', 'occurred_at', 'normalized_payload', 'payload']);

        foreach ($events as $ev) {
            $dt = Carbon::parse($ev->occurred_at);
            $key = match ($grouping) {
                'hour' => $dt->format('Y-m-d H:00'),
                'week' => $dt->format('Y-W'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };

            if (isset($slots[$key])) {
                $amount = $this->extractAmount($ev);
                if (!isset($slots[$key]['deposits'])) {
                    $slots[$key]['deposits'] = 0.0;
                    $slots[$key]['withdrawals'] = 0.0;
                    $slots[$key]['net'] = 0.0;
                }

                if ($depositTypeIds->contains($ev->event_type_id)) {
                    $slots[$key]['deposits'] += $amount;
                    $slots[$key]['net'] += $amount;
                } else {
                    $slots[$key]['withdrawals'] += $amount;
                    $slots[$key]['net'] -= $amount;
                }
            }
        }

        return array_values(array_map(function ($slot) {
            return [
                'key' => $slot['key'],
                'label' => $slot['label'],
                'date' => $slot['date'],
                'deposits' => round($slot['deposits'] ?? 0.0, 2),
                'withdrawals' => round($slot['withdrawals'] ?? 0.0, 2),
                'net' => round($slot['net'] ?? 0.0, 2),
            ];
        }, $slots));
    }

    protected function extractAmount(Event $event): float
    {
        $payload = $event->normalized_payload ?? $event->payload ?? [];
        if (isset($payload['data']['amount'])) {
            return (float) $payload['data']['amount'];
        }
        if (isset($payload['amount'])) {
            return (float) $payload['amount'];
        }
        return 0.0;
    }
}
