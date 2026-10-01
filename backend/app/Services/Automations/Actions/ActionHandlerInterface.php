<?php

namespace App\Services\Automations\Actions;

use App\Models\AutomationRun;
use App\Models\AutomationStep;

interface ActionHandlerInterface
{
    /**
     * Executa a ação da automação.
     * Retorna array com status, payload gerado e eventuais metadados.
     *
     * @param AutomationRun $run
     * @param AutomationStep $step
     * @param array $config
     * @return array
     */
    public function handle(AutomationRun $run, AutomationStep $step, array $config): array;
}
