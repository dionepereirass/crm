<?php

namespace App\Services\Automations\Actions;

use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\Segment;
use InvalidArgumentException;

class ExitSegmentAction implements ActionHandlerInterface
{
    public function handle(AutomationRun $run, AutomationStep $step, array $config): array
    {
        $player = $run->player;
        if (!$player) {
            return [
                'status' => 'FAILED',
                'error' => 'Jogador não associado à execução da automação.',
            ];
        }

        $segmentId = $config['segment_id'] ?? null;
        if (!$segmentId) {
            throw new InvalidArgumentException("ID do segmento não configurado na ação EXIT_SEGMENT.");
        }

        $segment = Segment::withoutGlobalScopes()
            ->where('platform_id', $run->platform_id)
            ->where('id', $segmentId)
            ->first();

        if (!$segment) {
            return [
                'status' => 'SKIPPED',
                'reason' => 'SEGMENT_NOT_FOUND',
                'message' => "Segmento #{$segmentId} não encontrado para saída.",
            ];
        }

        AutomationLog::log(
            $run->automation_id,
            'SEGMENT_EXITED',
            "Jogador #{$player->id} saiu do segmento '{$segment->name}' (#{$segment->id}) via automação.",
            'INFO',
            $run->id,
            $step->id,
            ['segment_id' => $segment->id, 'player_id' => $player->id]
        );

        return [
            'status' => 'COMPLETED',
            'segment_id' => $segment->id,
            'segment_name' => $segment->name,
            'player_id' => $player->id,
        ];
    }
}
