<?php

namespace App\Services\Automations;

use App\Models\Automation;
use App\Models\AutomationNode;
use App\Models\Provider;
use App\Models\Template;

class AutomationGraphValidator
{
    /**
     * Valida integralmente o grafo da automação e todas as suas configurações.
     */
    public function validate(Automation $automation): array
    {
        $errors = [];
        $nodes = $automation->nodes()->get();
        $edges = $automation->edges()->get();

        // 1. Validação de Nós
        if ($nodes->isEmpty()) {
            $errors[] = [
                'node' => 'graph',
                'code' => 'NO_NODES',
                'message' => 'A jornada precisa possuir ao menos um nó de gatilho.',
            ];
            return ['valid' => false, 'errors' => $errors];
        }

        $triggerNodes = $nodes->where('node_type.value', 'TRIGGER');
        if ($triggerNodes->count() === 0) {
            $triggerNodes = $nodes->filter(fn($n) => (string) ($n->node_type?->value ?? $n->node_type) === 'TRIGGER');
        }

        if ($triggerNodes->count() !== 1) {
            $errors[] = [
                'node' => 'graph',
                'code' => 'INVALID_TRIGGER_COUNT',
                'message' => "A jornada deve conter exatamente um nó de Gatilho (encontrados: {$triggerNodes->count()}).",
            ];
        }

        $nodesById = $nodes->keyBy('id');

        // 2. Validação de Conexões (Edges)
        foreach ($edges as $edge) {
            if (!$nodesById->has($edge->source_node_id)) {
                $errors[] = [
                    'node' => "edge-{$edge->id}",
                    'code' => 'INVALID_SOURCE_NODE',
                    'message' => "Aresta conecta um nó de origem inexistente (#{$edge->source_node_id}).",
                ];
            }
            if (!$nodesById->has($edge->target_node_id)) {
                $errors[] = [
                    'node' => "edge-{$edge->id}",
                    'code' => 'INVALID_TARGET_NODE',
                    'message' => "Aresta conecta um nó de destino inexistente (#{$edge->target_node_id}).",
                ];
            }
        }

        // 3. Detecção de Ciclos no Grafo (Cycle Detection com DFS)
        if ($this->hasCycle($nodes, $edges)) {
            $errors[] = [
                'node' => 'graph',
                'code' => 'AUTOMATION_GRAPH_INVALID',
                'message' => 'Ciclo proibido detectado no fluxo da automação. Jornadas em loop infinito não são permitidas.',
            ];
        }

        // 4. Validação Específica por Tipo de Nó
        foreach ($nodes as $node) {
            $nodeType = (string) ($node->node_type?->value ?? $node->node_type);
            $config = $node->configuration ?? [];

            switch ($nodeType) {
                case 'ACTION':
                    $this->validateActionNode($automation, $node, $config, $errors);
                    break;

                case 'CONDITION':
                    $this->validateConditionNode($node, $config, $errors);
                    break;

                case 'WAIT':
                    $this->validateWaitNode($node, $config, $errors);
                    break;

                case 'TRIGGER':
                    // Trigger validado pelo trigger_type da automação
                    break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function validateActionNode(Automation $automation, AutomationNode $node, array $config, array &$errors): void
    {
        $actionType = strtoupper($config['action_type'] ?? '');
        if (empty($actionType)) {
            $errors[] = [
                'node' => $node->node_key,
                'code' => 'MISSING_ACTION_TYPE',
                'message' => "Nó de ação '{$node->name}' não possui tipo configurado.",
            ];
            return;
        }

        if ($actionType === 'SEND_EMAIL') {
            $templateId = $config['template_id'] ?? null;
            if (!$templateId) {
                $errors[] = [
                    'node' => $node->node_key,
                    'code' => 'TEMPLATE_REQUIRED',
                    'message' => "Ação de envio de e-mail '{$node->name}' requer seleção de um template.",
                ];
            } else {
                $template = Template::withoutGlobalScopes()
                    ->where('platform_id', $automation->platform_id)
                    ->where('id', $templateId)
                    ->first();

                if (!$template) {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'TEMPLATE_NOT_FOUND',
                        'message' => "Template #{$templateId} não encontrado na plataforma.",
                    ];
                } elseif ($template->channel !== 'EMAIL') {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'TEMPLATE_CHANNEL_MISMATCH',
                        'message' => "Template '{$template->name}' é do canal {$template->channel}, incompatível com envio de e-mail.",
                    ];
                } elseif (!$template->publishedVersion()) {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'TEMPLATE_NOT_PUBLISHED',
                        'message' => "Template '{$template->name}' não possui nenhuma versão publicada.",
                    ];
                }
            }

            if (!empty($config['provider_id'])) {
                $provider = Provider::withoutGlobalScopes()
                    ->where('platform_id', $automation->platform_id)
                    ->where('id', $config['provider_id'])
                    ->first();

                if (!$provider || $provider->channel !== 'EMAIL' || !$provider->is_active) {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'PROVIDER_INVALID',
                        'message' => "Provedor #{$config['provider_id']} inválido ou inativo para canal E-mail.",
                    ];
                }
            }
        } elseif ($actionType === 'SEND_SMS') {
            $templateId = $config['template_id'] ?? null;
            if (!$templateId) {
                $errors[] = [
                    'node' => $node->node_key,
                    'code' => 'TEMPLATE_REQUIRED',
                    'message' => "Ação de envio de SMS '{$node->name}' requer seleção de um template.",
                ];
            } else {
                $template = Template::withoutGlobalScopes()
                    ->where('platform_id', $automation->platform_id)
                    ->where('id', $templateId)
                    ->first();

                if (!$template) {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'TEMPLATE_NOT_FOUND',
                        'message' => "Template #{$templateId} não encontrado na plataforma.",
                    ];
                } elseif ($template->channel !== 'SMS') {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'TEMPLATE_CHANNEL_MISMATCH',
                        'message' => "Template '{$template->name}' é do canal {$template->channel}, incompatível com envio de SMS.",
                    ];
                } elseif (!$template->publishedVersion()) {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'TEMPLATE_NOT_PUBLISHED',
                        'message' => "Template '{$template->name}' não possui nenhuma versão publicada.",
                    ];
                }
            }

            if (!empty($config['provider_id'])) {
                $provider = Provider::withoutGlobalScopes()
                    ->where('platform_id', $automation->platform_id)
                    ->where('id', $config['provider_id'])
                    ->first();

                if (!$provider || $provider->channel !== 'SMS' || !$provider->is_active) {
                    $errors[] = [
                        'node' => $node->node_key,
                        'code' => 'PROVIDER_INVALID',
                        'message' => "Provedor #{$config['provider_id']} inválido ou inativo para canal SMS.",
                    ];
                }
            }
        } elseif ($actionType === 'ADD_TAG' || $actionType === 'REMOVE_TAG') {
            if (empty($config['tag_id']) && empty($config['tag_name'])) {
                $errors[] = [
                    'node' => $node->node_key,
                    'code' => 'TAG_REQUIRED',
                    'message' => "Ação '{$node->name}' requer indicação de tag por ID ou nome.",
                ];
            }
        } elseif ($actionType === 'ENTER_SEGMENT' || $actionType === 'EXIT_SEGMENT') {
            if (empty($config['segment_id'])) {
                $errors[] = [
                    'node' => $node->node_key,
                    'code' => 'SEGMENT_REQUIRED',
                    'message' => "Ação '{$node->name}' requer especificação do ID do segmento.",
                ];
            }
        }
    }

    protected function validateConditionNode(AutomationNode $node, array $config, array &$errors): void
    {
        if (empty($config['rules_tree']) && empty($config['field']) && empty($config['segment_id'])) {
            $errors[] = [
                'node' => $node->node_key,
                'code' => 'CONDITION_EMPTY',
                'message' => "Nó de condição '{$node->name}' não possui regras configuradas.",
            ];
        }
    }

    protected function validateWaitNode(AutomationNode $node, array $config, array &$errors): void
    {
        $amount = (int) ($config['amount'] ?? 0);
        $unit = strtolower($config['unit'] ?? '');

        if ($amount <= 0) {
            $errors[] = [
                'node' => $node->node_key,
                'code' => 'INVALID_WAIT_AMOUNT',
                'message' => "Tempo de espera no nó '{$node->name}' deve ser maior que zero.",
            ];
        }

        if (!in_array($unit, ['minute', 'minutes', 'hour', 'hours', 'day', 'days'])) {
            $errors[] = [
                'node' => $node->node_key,
                'code' => 'INVALID_WAIT_UNIT',
                'message' => "Unidade de espera '{$unit}' inválida no nó '{$node->name}'. Use minutes, hours ou days.",
            ];
        }
    }

    /**
     * Algoritmo DFS com estados White/Gray/Black para detecção de ciclos em grafos direcionados.
     */
    protected function hasCycle($nodes, $edges): bool
    {
        $adj = [];
        foreach ($nodes as $node) {
            $adj[$node->id] = [];
        }

        foreach ($edges as $edge) {
            if (isset($adj[$edge->source_node_id])) {
                $adj[$edge->source_node_id][] = $edge->target_node_id;
            }
        }

        // Estados: 0 = unvisited (white), 1 = visiting (gray), 2 = visited (black)
        $visited = [];
        foreach ($nodes as $node) {
            $visited[$node->id] = 0;
        }

        foreach ($nodes as $node) {
            if ($visited[$node->id] === 0) {
                if ($this->dfsCycleCheck($node->id, $adj, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function dfsCycleCheck(int $nodeId, array &$adj, array &$visited): bool
    {
        $visited[$nodeId] = 1; // Gray

        foreach ($adj[$nodeId] ?? [] as $neighborId) {
            if (!isset($visited[$neighborId])) {
                continue;
            }

            if ($visited[$neighborId] === 1) {
                // Back-edge encontrada => Ciclo detectado!
                return true;
            }

            if ($visited[$neighborId] === 0) {
                if ($this->dfsCycleCheck($neighborId, $adj, $visited)) {
                    return true;
                }
            }
        }

        $visited[$nodeId] = 2; // Black
        return false;
    }
}
