<?php

namespace App\Services\Automations\NodeHandlers;

use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use InvalidArgumentException;

class WaitNodeHandler implements NodeHandlerInterface
{
    public function handle(AutomationRun $run, AutomationNode $node, AutomationStep $step): array
    {
        $config = $node->configuration ?? [];
        $amount = max(1, (int) ($config['amount'] ?? 1));
        $unit = strtolower($config['unit'] ?? 'minutes');

        $seconds = match ($unit) {
            'minute', 'minutes' => $amount * 60,
            'hour', 'hours' => $amount * 3600,
            'day', 'days' => $amount * 86400,
            default => throw new InvalidArgumentException("Unidade de tempo '{$unit}' inválida para nó WAIT."),
        };

        $scheduledFor = now()->addSeconds($seconds);

        AutomationLog::log(
            $run->automation_id,
            'WAIT_SCHEDULED',
            "Espera programada de {$amount} {$unit} para jogador #{$run->player_id}. Retomada prevista para: {$scheduledFor->toDateTimeString()}.",
            'INFO',
            $run->id,
            $step->id,
            [
                'amount' => $amount,
                'unit' => $unit,
                'delay_seconds' => $seconds,
                'scheduled_for' => $scheduledFor->toIso8601String(),
            ]
        );

        return [
            'status' => 'WAITING',
            'branch' => 'default',
            'output' => [
                'amount' => $amount,
                'unit' => $unit,
                'delay_seconds' => $seconds,
                'scheduled_for' => $scheduledFor->toIso8601String(),
            ],
            'delay_seconds' => $seconds,
            'error' => null,
        ];
    }
}
