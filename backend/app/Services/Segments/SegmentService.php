<?php

namespace App\Services\Segments;

use App\Models\Player;
use App\Models\Segment;
use App\Models\SegmentCondition;
use App\Models\SegmentGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SegmentService
{
    public function __construct(
        protected SegmentQueryCompiler $queryCompiler,
        protected SegmentCacheService $cacheService
    ) {}

    /**
     * Lista os segmentos da plataforma com filtros e paginação.
     */
    public function list(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Segment::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['creator:id,name,email', 'updater:id,name,email']);

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
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
     * Busca um segmento por ID ou UUID com isolamento por plataforma.
     */
    public function getById(int|string $id, int $platformId): Segment
    {
        $query = Segment::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['groups.conditions', 'creator:id,name,email', 'updater:id,name,email']);

        if (is_numeric($id)) {
            $segment = $query->where('id', (int) $id)->first();
        } else {
            $segment = $query->where('uuid', $id)->first();
        }

        if (!$segment) {
            throw new InvalidArgumentException("Segmento não encontrado para a plataforma atual.");
        }

        return $segment;
    }

    /**
     * Cria um novo segmento com AST de regras.
     */
    public function create(int $platformId, array $data, ?int $userId = null): Segment
    {
        $rulesTree = $data['rules_tree'] ?? ['operator' => 'AND', 'children' => []];
        $this->queryCompiler->validateRulesTree($rulesTree);

        return DB::transaction(function () use ($platformId, $data, $rulesTree, $userId) {
            $segment = Segment::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $platformId,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']) . '-' . Str::random(6),
                'description' => $data['description'] ?? null,
                'status' => strtoupper($data['status'] ?? 'DRAFT'),
                'rules_tree' => $rulesTree,
                'cached_count' => 0,
                'cached_at' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Sincroniza tabelas relacionais de grupos e condições
            $this->syncRelationalStructure($segment);

            // Calcula contagem inicial
            $this->refreshCount($segment);

            return $segment->fresh(['creator', 'updater']);
        });
    }

    /**
     * Atualiza um segmento existente.
     */
    public function update(Segment $segment, array $data, ?int $userId = null): Segment
    {
        if (isset($data['rules_tree'])) {
            $this->queryCompiler->validateRulesTree($data['rules_tree']);
        }

        return DB::transaction(function () use ($segment, $data, $userId) {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = strtoupper($data['status']);
            }
            if (isset($data['rules_tree'])) {
                $updateData['rules_tree'] = $data['rules_tree'];
            }
            if ($userId) {
                $updateData['updated_by'] = $userId;
            }

            $segment->update($updateData);

            if (isset($data['rules_tree'])) {
                $this->syncRelationalStructure($segment);
                $this->cacheService->invalidate($segment->platform_id, $segment->id);
                $this->refreshCount($segment);
            }

            return $segment->fresh(['creator', 'updater']);
        });
    }

    /**
     * Exclui (soft delete) um segmento.
     */
    public function delete(Segment $segment): bool
    {
        $this->cacheService->invalidate($segment->platform_id, $segment->id);
        return (bool) $segment->delete();
    }

    /**
     * Ativa um segmento.
     */
    public function activate(Segment $segment): Segment
    {
        $segment->update(['status' => 'ACTIVE']);
        $this->refreshCount($segment);
        return $segment;
    }

    /**
     * Desativa um segmento.
     */
    public function deactivate(Segment $segment): Segment
    {
        $segment->update(['status' => 'INACTIVE']);
        return $segment;
    }

    /**
     * Duplica um segmento.
     */
    public function duplicate(Segment $segment, ?int $userId = null): Segment
    {
        return DB::transaction(function () use ($segment, $userId) {
            $clone = Segment::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $segment->platform_id,
                'name' => $segment->name . ' (Cópia)',
                'slug' => Str::slug($segment->name . '-copia') . '-' . Str::random(6),
                'description' => $segment->description,
                'status' => 'DRAFT',
                'rules_tree' => $segment->rules_tree,
                'cached_count' => $segment->cached_count,
                'cached_at' => $segment->cached_at,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncRelationalStructure($clone);

            return $clone->fresh(['creator', 'updater']);
        });
    }

    /**
     * Executa preview dinâmico de regras sem persistir segmento.
     */
    public function preview(int $platformId, array $rulesTree, int $sampleLimit = 10): array
    {
        $startTime = microtime(true);

        $this->queryCompiler->validateRulesTree($rulesTree);

        $builder = $this->queryCompiler->compile($rulesTree, $platformId);
        $total = $builder->count();

        $sample = (clone $builder)
            ->with(['tags'])
            ->limit($sampleLimit)
            ->get();

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'count' => $total,
            'sample' => $sample,
            'execution_time_ms' => $executionTimeMs,
        ];
    }

    /**
     * Recalcula e atualiza a contagem do segmento (banco e cache Redis).
     */
    public function refreshCount(Segment $segment): int
    {
        $count = $this->queryCompiler->count($segment->rules_tree, $segment->platform_id);

        $segment->update([
            'cached_count' => $count,
            'cached_at' => now(),
        ]);

        $this->cacheService->put($segment->platform_id, $segment->id, $count);

        return $count;
    }

    /**
     * Retorna lista paginada de jogadores membros do segmento.
     */
    public function getMembers(Segment $segment, int $perPage = 25): LengthAwarePaginator
    {
        return $this->queryCompiler->compile($segment->rules_tree, $segment->platform_id)
            ->with(['tags'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Sincroniza tabelas relacionais de grupos e condições a partir do rules_tree.
     */
    public function syncRelationalStructure(Segment $segment): void
    {
        SegmentCondition::where('segment_id', $segment->id)->delete();
        SegmentGroup::where('segment_id', $segment->id)->delete();

        $rulesTree = $segment->rules_tree;
        if (empty($rulesTree) || empty($rulesTree['children'])) {
            return;
        }

        $this->persistGroupNode($segment->id, $rulesTree, null, 0);
    }

    protected function persistGroupNode(int $segmentId, array $node, ?int $parentId = null, int $position = 0): int
    {
        $group = SegmentGroup::create([
            'segment_id' => $segmentId,
            'parent_id' => $parentId,
            'logical_operator' => strtoupper($node['operator'] ?? 'AND'),
            'position' => $position,
        ]);

        $children = $node['children'] ?? [];
        $childPos = 0;

        foreach ($children as $child) {
            $childType = $child['type'] ?? 'condition';
            if ($childType === 'group') {
                $this->persistGroupNode($segmentId, $child, $group->id, $childPos++);
            } else {
                $field = $child['field'] ?? '';
                $operator = $child['operator'] ?? '';
                $value = $child['value'] ?? null;
                if (is_array($value)) {
                    $value = json_encode($value);
                } elseif (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                SegmentCondition::create([
                    'segment_id' => $segmentId,
                    'group_id' => $group->id,
                    'field' => $field,
                    'operator' => $operator,
                    'value' => (string) $value,
                    'value_type' => $child['value_type'] ?? 'STRING',
                    'event_type' => $child['event_type'] ?? null,
                    'period_value' => $child['period_value'] ?? null,
                    'period_unit' => $child['period_unit'] ?? null,
                    'position' => $childPos++,
                    'metadata' => $child['metadata'] ?? null,
                ]);
            }
        }

        return $group->id;
    }
}
