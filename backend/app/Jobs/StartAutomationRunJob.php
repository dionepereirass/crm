<?php

namespace App\Jobs;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationLog;
use App\Models\AutomationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartAutomationRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public int $automationRunId)
    {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $run = AutomationRun::withoutGlobalScopes()->with(['automation.nodes'])->find($this->automationRunId);
        if (!$run || $run->status !== AutomationRunStatus::RUNNING) {
            return;
        }

        $triggerNode = $run->automation->triggerNode();
        if (!$triggerNode) {
            $run->update([
                'status' => AutomationRunStatus::FAILED,
                'last_error' => 'Nenhum nó de gatilho configurado na automação.',
                'completed_at' => now(),
            ]);

            AutomationLog::log(
                $run->automation_id,
                'RUN_FAILED',
                "Execução #{$run->id} falhou: Nó de gatilho ausente.",
                'ERROR',
                $run->id
            );
            return;
        }

        $run->update(['current_node_id' => $triggerNode->id]);

        ProcessAutomationStepJob::dispatch($run->id, $triggerNode->id)->onQueue('automations');
    }
}
