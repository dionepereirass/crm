<?php

namespace App\Services\Segments;

class SegmentOperatorRegistry
{
    public static function all(): array
    {
        return [
            // Operadores Básicos de Comparação
            'equals' => [
                'name' => 'É igual a',
                'type' => 'basic',
                'description' => 'Compara igualdade exata.',
            ],
            'not_equals' => [
                'name' => 'Não é igual a',
                'type' => 'basic',
                'description' => 'Diferente do valor especificado.',
            ],
            'contains' => [
                'name' => 'Contém',
                'type' => 'text',
                'description' => 'Contém o termo de busca (parcial).',
            ],
            'not_contains' => [
                'name' => 'Não contém',
                'type' => 'text',
                'description' => 'Não contém o termo.',
            ],
            'starts_with' => [
                'name' => 'Começa com',
                'type' => 'text',
                'description' => 'Inicia com o texto.',
            ],
            'ends_with' => [
                'name' => 'Termina com',
                'type' => 'text',
                'description' => 'Finaliza com o texto.',
            ],
            'greater_than' => [
                'name' => 'Maior que',
                'type' => 'numeric',
                'description' => 'Valor estritamente maior que (>).',
            ],
            'greater_than_or_equal' => [
                'name' => 'Maior ou igual a',
                'type' => 'numeric',
                'description' => 'Valor maior ou igual (>=).',
            ],
            'less_than' => [
                'name' => 'Menor que',
                'type' => 'numeric',
                'description' => 'Valor estritamente menor que (<).',
            ],
            'less_than_or_equal' => [
                'name' => 'Menor ou igual a',
                'type' => 'numeric',
                'description' => 'Valor menor ou igual (<=).',
            ],
            'between' => [
                'name' => 'Está entre',
                'type' => 'numeric',
                'description' => 'Faixa numérica ou de datas (min e max).',
            ],
            'in' => [
                'name' => 'Contido na lista',
                'type' => 'list',
                'description' => 'Pertence a um conjunto de valores.',
            ],
            'not_in' => [
                'name' => 'Não contido na lista',
                'type' => 'list',
                'description' => 'Não pertence ao conjunto de valores.',
            ],
            'is_null' => [
                'name' => 'Não informado (vazio)',
                'type' => 'nullness',
                'description' => 'Campo nulo ou não preenchido.',
            ],
            'is_not_null' => [
                'name' => 'Preenchido (não vazio)',
                'type' => 'nullness',
                'description' => 'Campo preenchido.',
            ],

            // Operadores Temporais
            'today' => [
                'name' => 'Hoje',
                'type' => 'temporal',
                'description' => 'Data corrente.',
            ],
            'yesterday' => [
                'name' => 'Ontem',
                'type' => 'temporal',
                'description' => 'Dia imediatamente anterior.',
            ],
            'last_n_days' => [
                'name' => 'Nos últimos X dias',
                'type' => 'temporal',
                'description' => 'Janela móvel nos últimos N dias.',
            ],
            'last_n_hours' => [
                'name' => 'Nas últimas X horas',
                'type' => 'temporal',
                'description' => 'Janela móvel nas últimas N horas.',
            ],
            'last_n_weeks' => [
                'name' => 'Nas últimas X semanas',
                'type' => 'temporal',
                'description' => 'Janela móvel nas últimas N semanas.',
            ],
            'last_n_months' => [
                'name' => 'Nos últimos X meses',
                'type' => 'temporal',
                'description' => 'Janela móvel nos últimos N meses.',
            ],
            'before' => [
                'name' => 'Antes de',
                'type' => 'temporal',
                'description' => 'Anterior à data/hora especificada.',
            ],
            'after' => [
                'name' => 'Depois de',
                'type' => 'temporal',
                'description' => 'Posterior à data/hora especificada.',
            ],
            'between_dates' => [
                'name' => 'Entre as datas',
                'type' => 'temporal',
                'description' => 'Intervalo delimitado por data inicial e final.',
            ],

            // Operadores de Existência
            'exists' => [
                'name' => 'Existe / Ocorreu',
                'type' => 'existence',
                'description' => 'Existe pelo menos um registro ou evento.',
            ],
            'not_exists' => [
                'name' => 'Não existe / Não ocorreu',
                'type' => 'existence',
                'description' => 'Nenhum registro ou evento correspondente.',
            ],
        ];
    }

    public static function has(string $operator): bool
    {
        return array_key_exists($operator, self::all());
    }

    public static function get(string $operator): ?array
    {
        return self::all()[$operator] ?? null;
    }

    public static function grouped(): array
    {
        $all = self::all();
        $grouped = [
            'comparison' => [],
            'temporal' => [],
            'existence' => [],
        ];

        foreach ($all as $key => $item) {
            $item['key'] = $key;
            $type = $item['type'] ?? 'basic';
            if ($type === 'temporal') {
                $grouped['temporal'][$key] = $item;
            } elseif ($type === 'existence') {
                $grouped['existence'][$key] = $item;
            } else {
                $grouped['comparison'][$key] = $item;
            }
        }

        return $grouped;
    }
}

