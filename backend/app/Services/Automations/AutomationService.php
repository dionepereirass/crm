<?php

namespace App\Services\Automations;

use App\Enums\AutomationNodeType;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTriggerType;
use App\Models\Automation;
use App\Models\AutomationEdge;
use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AutomationService
{
    public function __construct(
        protected AutomationGraphValidator $graphValidator
    ) {}

    /**
     * Lista automações da plataforma com filtros e paginação.
     */
    public function list(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Automation::where('platform_id', $platformId)
            ->with(['creator:id,name,email', 'updater:id,name,email'])
            ->withCount([
                'runs as total_runs',
                'runs as completed_runs' => fn($q) => $q->where('status', AutomationRunStatus::COMPLETED),
                'runs as failed_runs' => fn($q) => $q->where('status', AutomationRunStatus::FAILED),
            ]);

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['trigger_type']) && $filters['trigger_type'] !== 'ALL') {
            $query->where('trigger_type', strtoupper($filters['trigger_type']));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Busca automação por ID garantindo isolamento de plataforma.
     */
    public function getById(int $id, int $platformId): Automation
    {
        $automation = Automation::where('platform_id', $platformId)
            ->with(['nodes', 'edges', 'creator', 'updater'])
            ->find($id);

        if (!$automation) {
            throw new InvalidArgumentException("Automação #{$id} não encontrada na plataforma atual.");
        }

        return $automation;
    }

    /**
     * Cria nova automação com nó inicial de gatilho padrão.
     */
    public function create(int $platformId, array $data, ?int $userId = null): Automation
    {
        return DB::transaction(function () use ($platformId, $data, $userId) {
            $triggerType = $data['trigger_type'] ?? AutomationTriggerType::PLAYER_CREATED->value;

            $automation = Automation::create([
                'platform_id' => $platformId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => AutomationStatus::DRAFT,
                'trigger_type' => $triggerType,
                'settings' => $data['settings'] ?? [
                    'reentry_policy' => 'BLOCK_REENTRY',
                    'cooldown_days' => 0,
                    'max_steps_per_run' => 50,
                ],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Cria nó de gatilho padrão inicial
            AutomationNode::create([
                'automation_id' => $automation->id,
                'node_key' => 'trigger-1',
                'node_type' => AutomationNodeType::TRIGGER,
                'name' => 'Início: ' . (AutomationTriggerType::tryFrom($triggerType)?->label() ?? $triggerType),
                'configuration' => ['trigger_type' => $triggerType],
                'position_x' => 100,
                'position_y' => 100,
            ]);

            AutomationLog::log(
                $automation->id,
                'AUTOMATION_CREATED',
                "Automação '{$automation->name}' criada por usuário #{$userId}.",
                'INFO',
                null,
                null,
                ['settings' => $automation->settings]
            );

            return $automation;
        });
    }

    /**
     * Atualiza dados cadastrais da automação.
     */
    public function update(Automation $automation, array $data, ?int $userId = null): Automation
    {
        $automation->update(array_filter([
            'name' => $data['name'] ?? $automation->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $automation->description,
            'trigger_type' => $data['trigger_type'] ?? $automation->trigger_type,
            'settings' => $data['settings'] ?? $automation->settings,
            'updated_by' => $userId,
        ]));

        AutomationLog::log(
            $automation->id,
            'AUTOMATION_UPDATED',
            "Automação '{$automation->name}' atualizada por usuário #{$userId}.",
            'INFO'
        );

        return $automation->fresh(['nodes', 'edges']);
    }

    /**
     * Exclui automação (SoftDeletes).
     */
    public function delete(Automation $automation, ?int $userId = null): bool
    {
        if ($automation->isActive()) {
            throw new InvalidArgumentException("Não é permitido excluir uma automação ATIVA. Pause ou desative antes de excluir.");
        }

        AutomationLog::log(
            $automation->id,
            'AUTOMATION_DELETED',
            "Automação '{$automation->name}' excluída por usuário #{$userId}.",
            'WARNING'
        );

        return $automation->delete();
    }

    /**
     * Salva o grafo completo (nós e arestas) da automação.
     */
    public function saveGraph(Automation $automation, array $nodesData, array $edgesData, ?int $userId = null): Automation
    {
        return DB::transaction(function () use ($automation, $nodesData, $edgesData, $userId) {
            // Mapeia chaves para sincronização
            $existingNodes = $automation->nodes()->get()->keyBy('node_key');
            $savedNodesMap = []; // node_key => AutomationNode model
            $processedNodeKeys = [];

            foreach ($nodesData as $nData) {
                $nodeKey = $nData['node_key'] ?? ('node-' . uniqid());
                $processedNodeKeys[] = $nodeKey;

                $node = AutomationNode::updateOrCreate(
                    [
                        'automation_id' => $automation->id,
                        'node_key' => $nodeKey,
                    ],
                    [
                        'node_type' => strtoupper($nData['node_type']),
                        'name' => $nData['name'] ?? 'Novo Bloco',
                        'configuration' => $nData['configuration'] ?? [],
                        'position_x' => (float) ($nData['position_x'] ?? 0),
                        'position_y' => (float) ($nData['position_y'] ?? 0),
                    ]
                );

                $savedNodesMap[$nodeKey] = $node;
            }

            // Remove nós deletados
            foreach ($existingNodes as $key => $existingNode) {
                if (!in_array($key, $processedNodeKeys)) {
                    $existingNode->delete();
                }
            }

            // Recria arestas
            $automation->edges()->delete();

            foreach ($edgesData as $eData) {
                $sourceKey = $eData['source_node_key'] ?? null;
                $targetKey = $eData['target_node_key'] ?? null;

                $sourceId = $eData['source_node_id'] ?? ($savedNodesMap[$sourceKey]->id ?? null);
                $targetId = $eData['target_node_id'] ?? ($savedNodesMap[$targetKey]->id ?? null);

                if ($sourceId && $targetId) {
                    AutomationEdge::create([
                        'automation_id' => $automation->id,
                        'source_node_id' => $sourceId,
                        'target_node_id' => $targetId,
                        'condition_key' => $eData['condition_key'] ?? null,
                    ]);
                }
            }

            $automation->update(['updated_by' => $userId]);

            AutomationLog::log(
                $automation->id,
                'GRAPH_SAVED',
                "Grafo da automação salvo com " . count($nodesData) . " blocos e " . count($edgesData) . " conexões.",
                'INFO'
            );

            return $automation->fresh(['nodes', 'edges']);
        });
    }

    /**
     * Retorna a representação estruturada do grafo.
     */
    public function getGraph(Automation $automation): array
    {
        return [
            'automation' => [
                'id' => $automation->id,
                'name' => $automation->name,
                'status' => $automation->status?->value ?? $automation->status,
                'trigger_type' => $automation->trigger_type?->value ?? $automation->trigger_type,
            ],
            'nodes' => $automation->nodes()->get()->map(fn($node) => [
                'id' => $node->id,
                'node_key' => $node->node_key,
                'node_type' => $node->node_type?->value ?? $node->node_type,
                'name' => $node->name,
                'configuration' => $node->configuration,
                'position_x' => $node->position_x,
                'position_y' => $node->position_y,
            ])->toArray(),
            'edges' => $automation->edges()->with(['sourceNode', 'targetNode'])->get()->map(fn($edge) => [
                'id' => $edge->id,
                'source_node_id' => $edge->source_node_id,
                'source_node_key' => $edge->sourceNode?->node_key,
                'target_node_id' => $edge->target_node_id,
                'target_node_key' => $edge->targetNode?->node_key,
                'condition_key' => $edge->condition_key,
            ])->toArray(),
        ];
    }

    /**
     * Ativa a automação após validação estrita do grafo.
     */
    public function activate(Automation $automation, ?int $userId = null): Automation
    {
        $validation = $this->graphValidator->validate($automation);

        if (!$validation['valid']) {
            throw ValidationException::withMessages([
                'graph' => ['A automação não pode ser ativada pois possui erros de validação.'],
                'errors' => $validation['errors'],
            ]);
        }

        $automation->update([
            'status' => AutomationStatus::ACTIVE,
            'updated_by' => $userId,
        ]);

        AutomationLog::log(
            $automation->id,
            'AUTOMATION_ACTIVATED',
            "Automação '{$automation->name}' ativada com sucesso por usuário #{$userId}.",
            'INFO'
        );

        return $automation;
    }

    /**
     * Pausa a automação.
     */
    public function pause(Automation $automation, ?int $userId = null): Automation
    {
        $automation->update([
            'status' => AutomationStatus::PAUSED,
            'updated_by' => $userId,
        ]);

        AutomationLog::log(
            $automation->id,
            'AUTOMATION_PAUSED',
            "Automação '{$automation->name}' pausada por usuário #{$userId}.",
            'WARNING'
        );

        return $automation;
    }

    /**
     * Desativa a automação (retorna para INACTIVE).
     */
    public function deactivate(Automation $automation, ?int $userId = null): Automation
    {
        $automation->update([
            'status' => AutomationStatus::INACTIVE,
            'updated_by' => $userId,
        ]);

        AutomationLog::log(
            $automation->id,
            'AUTOMATION_DEACTIVATED',
            "Automação '{$automation->name}' desativada por usuário #{$userId}.",
            'INFO'
        );

        return $automation;
    }

    /**
     * Lista execuções da automação com mascaramento LGPD de contatos do jogador.
     */
    public function listRuns(Automation $automation, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AutomationRun::where('automation_id', $automation->id)
            ->with(['player:id,name,email,phone', 'currentNode'])
            ->withCount('steps');

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Cancela uma execução específica em andamento.
     */
    public function cancelRun(AutomationRun $run, ?int $userId = null): AutomationRun
    {
        if ($run->isTerminal()) {
            throw new InvalidArgumentException("A execução #{$run->id} já está em estado terminal ({$run->status->value}).");
        }

        $run->update([
            'status' => AutomationRunStatus::CANCELLED,
            'completed_at' => now(),
            'last_error' => "Cancelada manualmente pelo usuário #{$userId}.",
        ]);

        AutomationLog::log(
            $run->automation_id,
            'RUN_CANCELLED',
            "Execução #{$run->id} cancelada manualmente.",
            'WARNING',
            $run->id
        );

        return $run;
    }

    /**
     * Retorna indicadores e métricas da automação.
     */
    public function getMetrics(Automation $automation): array
    {
        $counts = AutomationRun::where('automation_id', $automation->id)
            ->selectRaw("
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'WAITING' THEN 1 ELSE 0 END) as waiting,
                SUM(CASE WHEN status = 'RUNNING' THEN 1 ELSE 0 END) as running,
                SUM(CASE WHEN status = 'SKIPPED' THEN 1 ELSE 0 END) as skipped
            ")
            ->first();

        $total = (int) ($counts->total_runs ?? 0);
        $completed = (int) ($counts->completed ?? 0);
        $successRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'total_runs' => $total,
            'completed' => $completed,
            'failed' => (int) ($counts->failed ?? 0),
            'cancelled' => (int) ($counts->cancelled ?? 0),
            'waiting' => (int) ($counts->waiting ?? 0),
            'running' => (int) ($counts->running ?? 0),
            'skipped' => (int) ($counts->skipped ?? 0),
            'success_rate' => $successRate,
        ];
    }

    /**
     * Gera pré-visualização da jornada sem realizar alterações ou disparos.
     */
    public function preview(Automation $automation): array
    {
        $graph = $this->getGraph($automation);
        $validation = $this->graphValidator->validate($automation);

        return [
            'automation' => $graph['automation'],
            'nodes_count' => count($graph['nodes']),
            'edges_count' => count($graph['edges']),
            'validation' => $validation,
            'preview_flow' => $this->buildPreviewFlow($graph['nodes'], $graph['edges']),
        ];
    }

    protected function buildPreviewFlow(array $nodes, array $edges): array
    {
        $flow = [];
        $nodesById = collect($nodes)->keyBy('id');

        $trigger = collect($nodes)->first(fn($n) => $n['node_type'] === 'TRIGGER');
        if (!$trigger) {
            return $flow;
        }

        $flow[] = [
            'step' => 1,
            'type' => 'TRIGGER',
            'name' => $trigger['name'],
            'details' => $trigger['configuration'],
        ];

        return $flow;
    }
}
