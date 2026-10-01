<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CampaignStateService
{
    /**
     * Mapa de transições de estado permitidas na máquina de estados da campanha.
     */
    protected const ALLOWED_TRANSITIONS = [
        'DRAFT' => ['VALIDATING', 'READY', 'CANCELLED'],
        'VALIDATING' => ['READY', 'DRAFT', 'FAILED'],
        'READY' => ['SCHEDULED', 'PROCESSING', 'DRAFT', 'CANCELLED'],
        'SCHEDULED' => ['PROCESSING', 'READY', 'CANCELLED'],
        'PROCESSING' => ['PAUSED', 'COMPLETED', 'FAILED', 'CANCELLED'],
        'PAUSED' => ['PROCESSING', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
        'FAILED' => ['DRAFT'],
    ];

    /**
     * Verifica se a transição entre o status atual e o status de destino é permitida.
     */
    public function canTransition(Campaign $campaign, string $targetStatus): bool
    {
        $current = strtoupper($campaign->status);
        $target = strtoupper($targetStatus);

        if ($current === $target) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($target, $allowed, true);
    }

    /**
     * Executa a transição de estado da campanha de forma atômica e segura.
     *
     * @throws InvalidArgumentException
     */
    public function transition(Campaign $campaign, string $targetStatus, array $extraAttributes = []): Campaign
    {
        $current = strtoupper($campaign->status);
        $target = strtoupper($targetStatus);

        if (!$this->canTransition($campaign, $target)) {
            throw new InvalidArgumentException(
                "Transição de status inválida para a campanha #{$campaign->id}: de '{$current}' para '{$target}'."
            );
        }

        $attributes = array_merge(['status' => $target], $extraAttributes);

        // Atualiza carimbos temporais conforme o estado de destino
        match ($target) {
            'PROCESSING' => $attributes['started_at'] = $campaign->started_at ?? now(),
            'PAUSED' => $attributes['paused_at'] = now(),
            'COMPLETED' => $attributes['completed_at'] = now(),
            'CANCELLED' => $attributes['cancelled_at'] = now(),
            'FAILED' => $attributes['failed_at'] = now(),
            default => null,
        };

        // Se estiver retomando de PAUSED para PROCESSING, limpa paused_at
        if ($current === 'PAUSED' && $target === 'PROCESSING') {
            $attributes['paused_at'] = null;
        }

        $campaign->update($attributes);

        Log::info("Campanha #{$campaign->id} transitou de '{$current}' para '{$target}'.", [
            'campaign_id' => $campaign->id,
            'platform_id' => $campaign->platform_id,
            'previous_status' => $current,
            'new_status' => $target,
        ]);

        return $campaign->fresh();
    }
}
