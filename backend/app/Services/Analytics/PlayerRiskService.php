<?php

namespace App\Services\Analytics;

use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlayerRiskService
{
    /**
     * Classifica a base de jogadores em 4 perfis operacionais com base na recência de atividade e login.
     * Retorna a contagem e a proporção de cada grupo.
     */
    public function getRiskDistribution(int $platformId): array
    {
        $now = Carbon::now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $totalPlayers = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->count();

        if ($totalPlayers === 0) {
            return [
                'total' => 0,
                'ativo' => ['count' => 0, 'percentage' => 0.0],
                'atencao' => ['count' => 0, 'percentage' => 0.0],
                'risco' => ['count' => 0, 'percentage' => 0.0],
                'inativo' => ['count' => 0, 'percentage' => 0.0],
            ];
        }

        // 1. ATIVO: Atividade ou login nos últimos 7 dias
        $ativoCount = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($sevenDaysAgo) {
                $q->where('last_activity_at', '>=', $sevenDaysAgo)
                  ->orWhere('last_login_at', '>=', $sevenDaysAgo);
            })
            ->count();

        // 2. ATENÇÃO: Última atividade entre 8 e 14 dias atrás
        $atencaoCount = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($sevenDaysAgo, $fourteenDaysAgo) {
                $q->where(function ($sub) use ($sevenDaysAgo, $fourteenDaysAgo) {
                    $sub->where('last_activity_at', '>=', $fourteenDaysAgo)
                        ->where('last_activity_at', '<', $sevenDaysAgo);
                })->orWhere(function ($sub) use ($sevenDaysAgo, $fourteenDaysAgo) {
                    $sub->whereNull('last_activity_at')
                        ->where('last_login_at', '>=', $fourteenDaysAgo)
                        ->where('last_login_at', '<', $sevenDaysAgo);
                });
            })
            ->count();

        // 3. RISCO: Última atividade entre 15 e 30 dias atrás
        $riscoCount = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($fourteenDaysAgo, $thirtyDaysAgo) {
                $q->where(function ($sub) use ($fourteenDaysAgo, $thirtyDaysAgo) {
                    $sub->where('last_activity_at', '>=', $thirtyDaysAgo)
                        ->where('last_activity_at', '<', $fourteenDaysAgo);
                })->orWhere(function ($sub) use ($fourteenDaysAgo, $thirtyDaysAgo) {
                    $sub->whereNull('last_activity_at')
                        ->where('last_login_at', '>=', $thirtyDaysAgo)
                        ->where('last_login_at', '<', $fourteenDaysAgo);
                });
            })
            ->count();

        // 4. INATIVO: Mais de 30 dias sem atividade ou nunca logou
        $inativoCount = max(0, $totalPlayers - ($ativoCount + $atencaoCount + $riscoCount));

        return [
            'total' => $totalPlayers,
            'ativo' => [
                'count' => $ativoCount,
                'percentage' => round(($ativoCount / $totalPlayers) * 100, 1),
                'label' => 'Ativo (<= 7 dias)',
                'color' => '#10b981',
            ],
            'atencao' => [
                'count' => $atencaoCount,
                'percentage' => round(($atencaoCount / $totalPlayers) * 100, 1),
                'label' => 'Atenção (8 a 14 dias)',
                'color' => '#f59e0b',
            ],
            'risco' => [
                'count' => $riscoCount,
                'percentage' => round(($riscoCount / $totalPlayers) * 100, 1),
                'label' => 'Risco (15 a 30 dias)',
                'color' => '#f97316',
            ],
            'inativo' => [
                'count' => $inativoCount,
                'percentage' => round(($inativoCount / $totalPlayers) * 100, 1),
                'label' => 'Inativo (> 30 dias)',
                'color' => '#ef4444',
            ],
        ];
    }

    /**
     * Classifica um único jogador com base em seus atributos.
     */
    public function classifyPlayer(Player $player): string
    {
        $lastDate = $player->last_activity_at ?? $player->last_login_at;
        if (!$lastDate) {
            return 'INATIVO';
        }

        $days = Carbon::parse($lastDate)->diffInDays(Carbon::now());
        if ($days <= 7) {
            return 'ATIVO';
        }
        if ($days <= 14) {
            return 'ATENÇÃO';
        }
        if ($days <= 30) {
            return 'RISCO';
        }
        return 'INATIVO';
    }
}
