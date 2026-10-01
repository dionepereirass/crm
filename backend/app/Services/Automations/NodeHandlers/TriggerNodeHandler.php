<?php

namespace App\Services\Automations\NodeHandlers;

use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;

class TriggerNodeHandler implements NodeHandlerInterface
{
    public function handle(AutomationRun $run, AutomationNode $node, AutomationStep $step): array
    {
        $triggerType = $node->configuration['trigger_type'] ?? $run->automation->trigger_type?->value;

        AutomationLog::log(
            $run->automation_id,
            'TRIGGER_FIRED',
            "Gatilho '{$triggerType}' acionado para jogador #{$run->player_id}.",
            'INFO',
            $run->id,
            $step->id,
            [
                'trigger_type' => $triggerType,
                'player_id' => $run->player_id,
                'metadata' => $run->metadata,
            ]
        );

        return [
            'status' => 'COMPLETED',
            'branch' => 'default',
            'output' => [
                'trigger_type' => $triggerType,
                'player_id' => $run->player_id,
                'occurred_at' => now()->toIso8601String(),
            ],
            'error' => null,
        ];
    }
}
