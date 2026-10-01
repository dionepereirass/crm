<?php

namespace App\Jobs;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStepStatus;
use App\Models\AutomationEdge;
use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResumeAutomationRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $automationRunId,
        public int $waitNodeId
    ) {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $run = AutomationRun::withoutGlobalScopes()->with(['automation'])->find($this->automationRunId);
        if (!$run || $run->status !== AutomationRunStatus::WAITING) {
            // Execução já finalizada ou cancelada durante o tempo de espera
            return;
        }

        $waitNode = AutomationNode::find($this->waitNodeId);
        if (!$waitNode) {
            $run->update([
                'status' => AutomationRunStatus::FAILED,
                'last_error' => "Nó de espera #{$this->waitNodeId} não encontrado.",
                'completed_at' => now(),
            ]);
            return;
        }

        // Conclui o passo de espera
        $waitStep = AutomationStep::where('automation_run_id', $run->id)
            ->where('node_id', $waitNode->id)
            ->where('status', AutomationStepStatus::WAITING)
            ->latest('id')
            ->first();

        if ($waitStep) {
            $waitStep->update([
                'status' => AutomationStepStatus::COMPLETED,
                'completed_at' => now(),
            ]);
        }

        $run->update([
            'status' => AutomationRunStatus::RUNNING,
        ]);

        AutomationLog::log(
            $run->automation_id,
            'WAIT_RESUMED',
            "Período de espera finalizado para execução #{$run->id}. Retomando jornada.",
            'INFO',
            $run->id,
            $waitStep?->id
        );

        // Busca a próxima aresta após o nó de espera
        $nextEdge = AutomationEdge::where('source_node_id', $waitNode->id)->first();

        if ($nextEdge) {
            $run->update(['current_node_id' => $nextEdge->target_node_id]);
            ProcessAutomationStepJob::dispatch($run->id, $nextEdge->target_node_id)->onQueue('automations');
        } else {
            // Fim da jornada
            $run->update([
                'status' => AutomationRunStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            AutomationLog::log(
                $run->automation_id,
                'RUN_COMPLETED',
                "Execução #{$run->id} finalizada com sucesso após espera.",
                'INFO',
                $run->id
            );
        }
    }
}
