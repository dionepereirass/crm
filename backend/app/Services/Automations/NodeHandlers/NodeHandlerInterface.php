<?php

namespace App\Services\Automations\NodeHandlers;

use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;

interface NodeHandlerInterface
{
    /**
     * Processa a lógica de um nó específico da jornada.
     * Retorna array com:
     * - 'status' => AutomationStepStatus string (COMPLETED, WAITING, SKIPPED, FAILED)
     * - 'branch' => string ('true', 'false', 'default', null)
     * - 'output' => array
     * - 'error' => ?string
     * - 'delay_seconds' => ?int (apenas para nós de WAIT)
     *
     * @param AutomationRun $run
     * @param AutomationNode $node
     * @param AutomationStep $step
     * @return array
     */
    public function handle(AutomationRun $run, AutomationNode $node, AutomationStep $step): array;
}
