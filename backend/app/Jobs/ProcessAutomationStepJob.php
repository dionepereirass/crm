<?php

namespace App\Jobs;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStepStatus;
use App\Models\AutomationEdge;
use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Services\Automations\NodeHandlers\ActionNodeHandler;
use App\Services\Automations\NodeHandlers\ConditionNodeHandler;
use App\Services\Automations\NodeHandlers\TriggerNodeHandler;
use App\Services\Automations\NodeHandlers\WaitNodeHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessAutomationStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $automationRunId,
        public int $nodeId
    ) {
        $this->onQueue('automations');
    }

    public function handle(
        TriggerNodeHandler $triggerHandler,
        ConditionNodeHandler $conditionHandler,
        ActionNodeHandler $actionHandler,
        WaitNodeHandler $waitHandler
    ): void {
        $run = AutomationRun::withoutGlobalScopes()->with(['automation'])->find($this->automationRunId);
        if (!$run || in_array($run->status, [AutomationRunStatus::CANCELLED, AutomationRunStatus::FAILED, AutomationRunStatus::COMPLETED])) {
            return;
        }

        // Se a automação estiver PAUSADA, verifica se novas etapas devem esperar
        if ($run->automation->isPaused()) {
            AutomationLog::log(
                $run->automation_id,
                'RUN_PAUSED_WAITING',
                "Execução #{$run->id} retida pois a automação está pausada.",
                'WARNING',
                $run->id
            );
            return;
        }

        $node = AutomationNode::find($this->nodeId);
        if (!$node) {
            $run->update([
                'status' => AutomationRunStatus::FAILED,
                'last_error' => "Nó #{$this->nodeId} não encontrado.",
                'completed_at' => now(),
            ]);
            return;
        }

        // 1. Proteção Estrita contra Loops Infinitos
        $maxSteps = (int) ($run->automation->settings['max_steps_per_run'] ?? 50);
        $totalStepsCount = $run->steps()->count();
        if ($totalStepsCount >= $maxSteps) {
            $run->update([
                'status' => AutomationRunStatus::FAILED,
                'last_error' => "MAX_STEPS_EXCEEDED: Limite máximo de {$maxSteps} passos excedido na jornada.",
                'completed_at' => now(),
            ]);

            AutomationLog::log(
                $run->automation_id,
                'LOOP_PROTECTION_TRIGGERED',
                "Execução #{$run->id} abortada por exceder o limite de {$maxSteps} passos.",
                'ERROR',
                $run->id
            );
            return;
        }

        // 2. Registro do Passo
        $step = AutomationStep::create([
            'automation_run_id' => $run->id,
            'node_id' => $node->id,
            'status' => AutomationStepStatus::PROCESSING,
            'input' => $node->configuration,
            'started_at' => now(),
        ]);

        $run->update(['current_node_id' => $node->id]);

        // 3. Execução do Handler do Nó
        $nodeType = (string) ($node->node_type?->value ?? $node->node_type);

        try {
            $result = match ($nodeType) {
                'TRIGGER' => $triggerHandler->handle($run, $node, $step),
                'CONDITION' => $conditionHandler->handle($run, $node, $step),
                'ACTION' => $actionHandler->handle($run, $node, $step),
                'WAIT' => $waitHandler->handle($run, $node, $step),
                default => throw new \InvalidArgumentException("Tipo de nó desconhecido: {$nodeType}"),
            };

            $stepStatusStr = $result['status'] ?? 'COMPLETED';
            $stepStatus = AutomationStepStatus::tryFrom($stepStatusStr) ?? AutomationStepStatus::COMPLETED;

            $step->update([
                'status' => $stepStatus,
                'output' => $result['output'] ?? [],
                'error' => $result['error'] ?? null,
                'completed_at' => $stepStatus === AutomationStepStatus::WAITING ? null : now(),
            ]);

            // 4. Tratamento do Caso de Espera (WAIT)
            if ($stepStatus === AutomationStepStatus::WAITING) {
                $run->update([
                    'status' => AutomationRunStatus::WAITING,
                ]);

                $delaySeconds = (int) ($result['delay_seconds'] ?? 60);
                ResumeAutomationRunJob::dispatch($run->id, $node->id)
                    ->delay(now()->addSeconds($delaySeconds))
                    ->onQueue('automations');
                return;
            }

            // 5. Tratamento de Falha
            if ($stepStatus === AutomationStepStatus::FAILED) {
                $run->update([
                    'status' => AutomationRunStatus::FAILED,
                    'last_error' => $result['error'] ?? 'Falha na execução do passo.',
                    'completed_at' => now(),
                ]);
                return;
            }

            // 6. Resolução da Próxima Aresta / Ramo
            $branch = $result['branch'] ?? 'default';
            $nextEdge = $this->resolveNextEdge($node, $branch);

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
                    "Execução #{$run->id} finalizada com sucesso.",
                    'INFO',
                    $run->id
                );
            }

        } catch (Throwable $e) {
            $step->update([
                'status' => AutomationStepStatus::FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $run->update([
                'status' => AutomationRunStatus::FAILED,
                'last_error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            AutomationLog::log(
                $run->automation_id,
                'STEP_EXCEPTION',
                "Exceção no passo do nó #{$node->id}: {$e->getMessage()}",
                'ERROR',
                $run->id,
                $step->id,
                ['exception' => $e->getMessage()]
            );
        }
    }

    protected function resolveNextEdge(AutomationNode $node, string $branch): ?AutomationEdge
    {
        // 1. Busca aresta exata para o ramo (ex: 'true', 'false')
        $edge = AutomationEdge::where('source_node_id', $node->id)
            ->where('condition_key', $branch)
            ->first();

        if ($edge) {
            return $edge;
        }

        // 2. Busca aresta sem condição específica ou 'default'
        return AutomationEdge::where('source_node_id', $node->id)
            ->where(function ($q) {
                $q->whereNull('condition_key')
                  ->orWhere('condition_key', '')
                  ->orWhere('condition_key', 'default');
            })
            ->first();
    }
}
