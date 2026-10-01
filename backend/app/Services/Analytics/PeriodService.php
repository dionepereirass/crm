<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PeriodService
{
    /**
     * Resolve o intervalo de datas atual e o período anterior equivalente para comparação.
     * 
     * Retorna:
     * [
     *   'current' => [Carbon $start, Carbon $end],
     *   'prior'   => [Carbon $start, Carbon $end],
     *   'grouping' => string ('hour'|'day'|'week'|'month'),
     *   'period_key' => string
     * ]
     */
    public function resolvePeriod(array $filters = []): array
    {
        $periodKey = $filters['period'] ?? '30d';
        $now = Carbon::now('America/Sao_Paulo');

        switch ($periodKey) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $priorStart = $start->copy()->subDay();
                $priorEnd = $end->copy()->subDay();
                $defaultGrouping = 'hour';
                break;

            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $priorStart = $start->copy()->subDay();
                $priorEnd = $end->copy()->subDay();
                $defaultGrouping = 'hour';
                break;

            case '7d':
                $end = $now->copy()->endOfDay();
                $start = $now->copy()->subDays(6)->startOfDay();
                $diffDays = 7;
                $priorEnd = $start->copy()->subSecond();
                $priorStart = $priorEnd->copy()->subDays($diffDays)->addSecond()->startOfDay();
                $defaultGrouping = 'day';
                break;

            case '90d':
                $end = $now->copy()->endOfDay();
                $start = $now->copy()->subDays(89)->startOfDay();
                $diffDays = 90;
                $priorEnd = $start->copy()->subSecond();
                $priorStart = $priorEnd->copy()->subDays($diffDays)->addSecond()->startOfDay();
                $defaultGrouping = 'week';
                break;

            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $priorStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
                $priorEnd = $priorStart->copy()->endOfMonth();
                $defaultGrouping = 'day';
                break;

            case 'last_month':
                $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $priorStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
                $priorEnd = $priorStart->copy()->endOfMonth();
                $defaultGrouping = 'day';
                break;

            case 'custom':
                $start = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : $now->copy()->subDays(29)->startOfDay();
                $end = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : $now->copy()->endOfDay();
                $durationInSeconds = $end->diffInSeconds($start);
                $priorEnd = $start->copy()->subSecond();
                $priorStart = $priorEnd->copy()->subSeconds($durationInSeconds);
                $diffDays = $end->diffInDays($start);
                $defaultGrouping = $this->determineGrouping($diffDays);
                break;

            case '30d':
            default:
                $periodKey = '30d';
                $end = $now->copy()->endOfDay();
                $start = $now->copy()->subDays(29)->startOfDay();
                $diffDays = 30;
                $priorEnd = $start->copy()->subSecond();
                $priorStart = $priorEnd->copy()->subDays($diffDays)->addSecond()->startOfDay();
                $defaultGrouping = 'day';
                break;
        }

        $grouping = $filters['grouping'] ?? $defaultGrouping;

        return [
            'period_key' => $periodKey,
            'current' => [
                'start' => $start,
                'end' => $end,
                'iso_start' => $start->toIso8601String(),
                'iso_end' => $end->toIso8601String(),
            ],
            'prior' => [
                'start' => $priorStart,
                'end' => $priorEnd,
                'iso_start' => $priorStart->toIso8601String(),
                'iso_end' => $priorEnd->toIso8601String(),
            ],
            'grouping' => $grouping,
        ];
    }

    /**
     * Determina automaticamente o agrupamento com base na duração do intervalo em dias.
     */
    public function determineGrouping(int $diffDays): string
    {
        if ($diffDays <= 2) {
            return 'hour';
        }
        if ($diffDays <= 60) {
            return 'day';
        }
        if ($diffDays <= 180) {
            return 'week';
        }
        return 'month';
    }

    /**
     * Calcula variação percentual segura evitando divisão por zero.
     */
    public function calculatePercentageChange(float|int $current, float|int $prior): ?float
    {
        if ($prior == 0) {
            return null; // Sinaliza que não há histórico suficiente para comparação percentual
        }

        return round((($current - $prior) / $prior) * 100, 1);
    }

    /**
     * Gera os pontos temporais vazios (slots) para garantir gráficos contínuos.
     */
    public function generateDateSlots(Carbon $start, Carbon $end, string $grouping): array
    {
        $slots = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = match ($grouping) {
                'hour' => $cursor->format('Y-m-d H:00'),
                'week' => $cursor->format('Y-W'),
                'month' => $cursor->format('Y-m'),
                default => $cursor->format('Y-m-d'),
            };

            $label = match ($grouping) {
                'hour' => $cursor->format('H:i'),
                'week' => 'Sem ' . $cursor->format('W/y'),
                'month' => $cursor->translatedFormat('M/Y'),
                default => $cursor->format('d/m'),
            };

            $slots[$key] = [
                'key' => $key,
                'label' => $label,
                'date' => $cursor->toDateString(),
                'value' => 0,
            ];

            match ($grouping) {
                'hour' => $cursor->addHour(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $slots;
    }
}
