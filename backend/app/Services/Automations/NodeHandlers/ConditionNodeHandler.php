<?php

namespace App\Services\Automations\NodeHandlers;

use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\Message;
use App\Models\Segment;
use App\Services\Segments\SegmentFieldRegistry;
use App\Services\Segments\SegmentQueryCompiler;
use Throwable;

class ConditionNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        protected SegmentQueryCompiler $queryCompiler
    ) {}

    public function handle(AutomationRun $run, AutomationNode $node, AutomationStep $step): array
    {
        $config = $node->configuration ?? [];
        $result = false;

        try {
            $result = $this->evaluateCondition($run, $config);
        } catch (Throwable $e) {
            AutomationLog::log(
                $run->automation_id,
                'CONDITION_ERROR',
                "Erro ao avaliar condição do nó #{$node->id}: {$e->getMessage()}",
                'ERROR',
                $run->id,
                $step->id,
                ['error' => $e->getMessage(), 'config' => $config]
            );

            return [
                'status' => 'FAILED',
                'branch' => 'false',
                'output' => ['result' => false, 'error' => $e->getMessage()],
                'error' => $e->getMessage(),
            ];
        }

        $branch = $result ? 'true' : 'false';

        AutomationLog::log(
            $run->automation_id,
            'CONDITION_EVALUATED',
            "Condição avaliada no nó #{$node->id} para jogador #{$run->player_id}: resultado = " . ($result ? 'VERDADEIRO (true)' : 'FALSO (false)'),
            'INFO',
            $run->id,
            $step->id,
            [
                'node_key' => $node->node_key,
                'result' => $result,
                'branch' => $branch,
            ]
        );

        return [
            'status' => 'COMPLETED',
            'branch' => $branch,
            'output' => [
                'result' => $result,
                'evaluated_at' => now()->toIso8601String(),
            ],
            'error' => null,
        ];
    }

    protected function evaluateCondition(AutomationRun $run, array $config): bool
    {
        $playerId = $run->player_id;
        $platformId = $run->platform_id;

        // 1. Caso seja verificação direta de segmento
        if (!empty($config['segment_id']) || (!empty($config['field']) && in_array($config['field'], ['belongs_to_segment', 'does_not_belong_to_segment']))) {
            $segmentId = $config['segment_id'] ?? $config['value'];
            $segment = Segment::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('id', $segmentId)
                ->first();

            if (!$segment) {
                return false;
            }

            $inSegment = $this->queryCompiler->compile($segment->rules_tree, $platformId)
                ->where('players.id', $playerId)
                ->exists();

            $isNegative = ($config['field'] ?? '') === 'does_not_belong_to_segment' || ($config['operator'] ?? '') === 'not_in';
            return $isNegative ? !$inSegment : $inSegment;
        }

        // 2. Caso seja evento de campanha (campaign_delivered, campaign_opened, campaign_clicked)
        $field = $config['field'] ?? null;
        if (in_array($field, ['campaign_delivered', 'campaign_opened', 'campaign_clicked'])) {
            $campaignId = $config['campaign_id'] ?? $config['value'] ?? null;
            $eventType = match($field) {
                'campaign_delivered' => 'DELIVERED',
                'campaign_opened' => 'OPENED',
                'campaign_clicked' => 'CLICKED',
            };

            $query = Message::withoutGlobalScopes()
                ->where('platform_id', $platformId)
                ->where('player_id', $playerId);

            if ($campaignId) {
                $query->where('campaign_id', $campaignId);
            }

            return $query->whereHas('events', function ($q) use ($eventType) {
                $q->where('event_type', $eventType);
            })->exists();
        }

        // 3. Caso possua uma árvore estruturada de regras (rules_tree)
        if (!empty($config['rules_tree'])) {
            return $this->queryCompiler->compile($config['rules_tree'], $platformId)
                ->where('players.id', $playerId)
                ->exists();
        }

        // 4. Caso seja uma regra atômica: field, operator, value
        if (!empty($config['field']) && !empty($config['operator'])) {
            $normalizedField = SegmentFieldRegistry::normalize($config['field']);
            $mappedOperator = $this->mapOperator($config['operator']);

            $rulesTree = [
                'operator' => 'AND',
                'children' => [
                    [
                        'field' => $normalizedField,
                        'operator' => $mappedOperator,
                        'value' => $config['value'] ?? null,
                    ]
                ]
            ];

            return $this->queryCompiler->compile($rulesTree, $platformId)
                ->where('players.id', $playerId)
                ->exists();
        }

        return false;
    }

    protected function mapOperator(string $op): string
    {
        return match($op) {
            '=', '==' => 'equals',
            '!=', '<>' => 'not_equals',
            '>' => 'greater_than',
            '>=' => 'greater_than_or_equal',
            '<' => 'less_than',
            '<=' => 'less_than_or_equal',
            'contains' => 'contains',
            'not_contains' => 'not_contains',
            'starts_with' => 'starts_with',
            'ends_with' => 'ends_with',
            'in' => 'in',
            'not_in' => 'not_in',
            'exists', 'is_not_null' => 'is_not_null',
            'not_exists', 'is_null' => 'is_null',
            'between' => 'between',
            default => $op,
        };
    }
}
