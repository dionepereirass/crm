<?php

namespace App\Services\Segments;

use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SegmentQueryCompiler
{
    public function __construct(
        protected SegmentFieldRegistry $fieldRegistry,
        protected SegmentOperatorRegistry $operatorRegistry
    ) {}

    /**
     * Compila o AST/rules_tree em uma query Eloquent Builder com isolamento estrito de plataforma.
     */
    public function compile(array $rulesTree, int $platformId): Builder
    {
        $this->validateRulesTree($rulesTree);

        $query = Player::withoutGlobalScopes()
            ->where('platform_id', $platformId);

        $rootOperator = strtoupper($rulesTree['operator'] ?? 'AND');
        $children = $rulesTree['children'] ?? [];

        $query->where(function (Builder $groupQuery) use ($children, $rootOperator, $platformId) {
            $this->applyGroupChildren($groupQuery, $children, $rootOperator, $platformId);
        });

        return $query;
    }

    /**
     * Retorna a contagem exata de membros do segmento compilado.
     */
    public function count(array $rulesTree, int $platformId): int
    {
        return $this->compile($rulesTree, $platformId)->count();
    }

    /**
     * Retorna os IDs dos jogadores qualificados no segmento.
     */
    public function pluckIds(array $rulesTree, int $platformId, int $limit = 5000): array
    {
        return $this->compile($rulesTree, $platformId)
            ->limit($limit)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Valida a integridade da estrutura em árvore de regras.
     */
    public function validateRulesTree(array $rulesTree): void
    {
        if (empty($rulesTree)) {
            throw new InvalidArgumentException('A árvore de regras (rules_tree) não pode estar vazia.');
        }

        $operator = strtoupper($rulesTree['operator'] ?? '');
        if (!in_array($operator, ['AND', 'OR'])) {
            throw new InvalidArgumentException("Operador lógico de grupo inválido: '{$operator}'. Use AND ou OR.");
        }

        if (!isset($rulesTree['children']) || !is_array($rulesTree['children'])) {
            throw new InvalidArgumentException("O nó do grupo deve conter um array 'children'.");
        }

        foreach ($rulesTree['children'] as $child) {
            $this->validateChildNode($child);
        }
    }

    protected function validateChildNode(array $node): void
    {
        $type = $node['type'] ?? 'condition';

        if ($type === 'group') {
            $this->validateRulesTree($node);
            return;
        }

        // Validação de nó de condição atômica
        $field = $node['field'] ?? null;
        $operator = $node['operator'] ?? null;

        if (!$field || !SegmentFieldRegistry::has($field)) {
            throw new InvalidArgumentException("Campo de segmentação não reconhecido ou inválido: '{$field}'.");
        }

        if (!$operator || !SegmentOperatorRegistry::has($operator)) {
            throw new InvalidArgumentException("Operador de segmentação não reconhecido ou inválido: '{$operator}'.");
        }
    }

    protected function applyGroupChildren(Builder $query, array $children, string $logicalOperator, int $platformId): void
    {
        foreach ($children as $child) {
            $type = $child['type'] ?? 'condition';
            $isOr = ($logicalOperator === 'OR');

            if ($type === 'group') {
                $subOperator = strtoupper($child['operator'] ?? 'AND');
                $subChildren = $child['children'] ?? [];

                $callback = function (Builder $subQuery) use ($subChildren, $subOperator, $platformId) {
                    $this->applyGroupChildren($subQuery, $subChildren, $subOperator, $platformId);
                };

                if ($isOr) {
                    $query->orWhere($callback);
                } else {
                    $query->where($callback);
                }
            } else {
                $this->applyCondition($query, $child, $isOr, $platformId);
            }
        }
    }

    protected function applyCondition(Builder $query, array $condition, bool $isOr, int $platformId): void
    {
        $field = SegmentFieldRegistry::normalize($condition['field'] ?? '');
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;
        $period = $condition['period'] ?? null;

        $callback = function (Builder $q) use ($field, $operator, $value, $period, $platformId) {
            if (str_starts_with($field, 'player.')) {
                $this->applyPlayerFieldCondition($q, $field, $operator, $value, $platformId);
            } elseif (str_starts_with($field, 'consent.')) {
                $this->applyConsentCondition($q, $field, $operator, $value);
            } elseif (
                str_starts_with($field, 'deposit.') ||
                str_starts_with($field, 'bet.') ||
                str_starts_with($field, 'withdrawal.') ||
                str_starts_with($field, 'login.') ||
                str_starts_with($field, 'event.')
            ) {
                $this->applyEventAggregateCondition($q, $field, $operator, $value, $period, $platformId);
            }
        };

        if ($isOr) {
            $query->orWhere($callback);
        } else {
            $query->where($callback);
        }
    }

    protected function applyPlayerFieldCondition(Builder $query, string $field, string $operator, mixed $value, int $platformId): void
    {
        // 1. Tratamento Especial para Tags
        if ($field === 'player.has_tag') {
            $query->whereHas('tags', function (Builder $tagQuery) use ($value, $operator, $platformId) {
                $tagQuery->where('tags.platform_id', $platformId);
                if (is_numeric($value)) {
                    $tagQuery->where('tags.id', (int) $value);
                } else {
                    $this->applyStandardOperator($tagQuery, 'tags.name', $operator, $value);
                }
            });
            return;
        }

        if ($field === 'player.not_has_tag') {
            $query->whereDoesntHave('tags', function (Builder $tagQuery) use ($value, $operator, $platformId) {
                $tagQuery->where('tags.platform_id', $platformId);
                if (is_numeric($value)) {
                    $tagQuery->where('tags.id', (int) $value);
                } else {
                    $this->applyStandardOperator($tagQuery, 'tags.name', $operator, $value);
                }
            });
            return;
        }

        // 2. Mapeamento de Coluna da tabela players
        $columnMap = [
            'player.name' => 'name',
            'player.email' => 'email',
            'player.phone' => 'phone',
            'player.cpf' => 'cpf',
            'player.birth_date' => 'birth_date',
            'player.gender' => 'gender',
            'player.state' => 'state',
            'player.city' => 'city',
            'player.status' => 'status',
            'player.affiliate' => 'affiliate',
            'player.created_at' => 'created_at',
            'player.last_login_at' => 'last_login_at',
        ];

        $column = $columnMap[$field] ?? null;
        if (!$column) {
            return;
        }

        $this->applyStandardOperator($query, $column, $operator, $value);
    }

    protected function applyConsentCondition(Builder $query, string $field, string $operator, mixed $value): void
    {
        $channel = ($field === 'consent.marketing') ? 'email' : 'sms';
        $isGranted = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($isGranted) {
            $query->whereHas('consents', function (Builder $q) use ($channel) {
                $q->where('channel', $channel)->where('status', 'granted');
            });
        } else {
            $query->whereDoesntHave('consents', function (Builder $q) use ($channel) {
                $q->where('channel', $channel)->where('status', 'granted');
            });
        }
    }

    protected function applyEventAggregateCondition(
        Builder $query,
        string $field,
        string $operator,
        mixed $value,
        ?array $period,
        int $platformId
    ): void {
        [$category, $metric] = explode('.', $field, 2);

        $eventTypeKey = match ($category) {
            'deposit' => 'DEPOSIT_SUCCESS',
            'bet' => 'BET_PLACED',
            'withdrawal' => 'WITHDRAWAL_SUCCESS',
            'login' => 'LOGIN',
            'event' => is_string($value) && in_array(strtoupper($value), ['DEPOSIT_SUCCESS', 'BET_PLACED', 'BET_SETTLED', 'WITHDRAWAL_SUCCESS', 'LOGIN', 'PLAYER_CREATED', 'PLAYER_UPDATED'])
                ? strtoupper($value)
                : 'DEPOSIT_SUCCESS',
            default => 'DEPOSIT_SUCCESS',
        };

        // Resolução da data de início para janela temporal
        $cutoffDate = $this->calculateCutoffDate($period);

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $jsonExtractSql = $isSqlite
            ? "json_extract(e.normalized_payload, '$.data.amount')"
            : "CAST(e.normalized_payload->'data'->>'amount' AS numeric)";

        // Construção do fragmento SQL de agregação correlacionada
        switch ($metric) {
            case 'total':
                $subquery = "SELECT COALESCE(SUM({$jsonExtractSql}), 0) FROM events e INNER JOIN event_types et ON et.id = e.event_type_id WHERE e.player_id = players.id AND e.platform_id = ? AND et.key = ? AND e.processing_status = 'PROCESSED'";
                $bindings = [$platformId, $eventTypeKey];

                if ($cutoffDate) {
                    $subquery .= " AND e.occurred_at >= ?";
                    $bindings[] = $cutoffDate->toDateTimeString();
                }

                $this->applyRawComparison($query, "({$subquery})", $operator, (float) $value, $bindings);
                break;

            case 'count':
                $subquery = "SELECT COUNT(*) FROM events e INNER JOIN event_types et ON et.id = e.event_type_id WHERE e.player_id = players.id AND e.platform_id = ? AND et.key = ? AND e.processing_status = 'PROCESSED'";
                $bindings = [$platformId, $eventTypeKey];

                if ($cutoffDate) {
                    $subquery .= " AND e.occurred_at >= ?";
                    $bindings[] = $cutoffDate->toDateTimeString();
                }

                $this->applyRawComparison($query, "({$subquery})", $operator, (int) $value, $bindings);
                break;

            case 'average':
                $subquery = "SELECT COALESCE(AVG({$jsonExtractSql}), 0) FROM events e INNER JOIN event_types et ON et.id = e.event_type_id WHERE e.player_id = players.id AND e.platform_id = ? AND et.key = ? AND e.processing_status = 'PROCESSED'";
                $bindings = [$platformId, $eventTypeKey];

                if ($cutoffDate) {
                    $subquery .= " AND e.occurred_at >= ?";
                    $bindings[] = $cutoffDate->toDateTimeString();
                }

                $this->applyRawComparison($query, "({$subquery})", $operator, (float) $value, $bindings);
                break;

            case 'last_amount':
                $subquery = "SELECT {$jsonExtractSql} FROM events e INNER JOIN event_types et ON et.id = e.event_type_id WHERE e.player_id = players.id AND e.platform_id = ? AND et.key = ? AND e.processing_status = 'PROCESSED' ORDER BY e.occurred_at DESC LIMIT 1";
                $bindings = [$platformId, $eventTypeKey];

                $this->applyRawComparison($query, "COALESCE(({$subquery}), 0)", $operator, (float) $value, $bindings);
                break;

            case 'last_at':
                $subquery = "SELECT MAX(e.occurred_at) FROM events e INNER JOIN event_types et ON et.id = e.event_type_id WHERE e.player_id = players.id AND e.platform_id = ? AND et.key = ? AND e.processing_status = 'PROCESSED'";
                $bindings = [$platformId, $eventTypeKey];

                $this->applyRawTemporalComparison($query, "({$subquery})", $operator, $value, $bindings);
                break;

            case 'occurred':
                // Evento ocorreu (exists / not_exists)
                $subquery = "SELECT 1 FROM events e INNER JOIN event_types et ON et.id = e.event_type_id WHERE e.player_id = players.id AND e.platform_id = ? AND et.key = ? AND e.processing_status = 'PROCESSED'";
                $bindings = [$platformId, $eventTypeKey];

                if ($cutoffDate) {
                    $subquery .= " AND e.occurred_at >= ?";
                    $bindings[] = $cutoffDate->toDateTimeString();
                }

                if ($operator === 'not_exists') {
                    $query->whereRaw("NOT EXISTS ({$subquery})", $bindings);
                } else {
                    $query->whereRaw("EXISTS ({$subquery})", $bindings);
                }
                break;
        }
    }

    protected function calculateCutoffDate(?array $period): ?Carbon
    {
        if (empty($period) || empty($period['value'])) {
            return null;
        }

        $num = (int) $period['value'];
        $unit = strtolower($period['unit'] ?? 'days');

        return match ($unit) {
            'hours' => Carbon::now('UTC')->subHours($num),
            'weeks' => Carbon::now('UTC')->subWeeks($num),
            'months' => Carbon::now('UTC')->subMonths($num),
            default => Carbon::now('UTC')->subDays($num),
        };
    }

    protected function applyStandardOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        switch ($operator) {
            case 'equals':
                $query->where($column, '=', $value);
                break;
            case 'not_equals':
                $query->where($column, '!=', $value);
                break;
            case 'contains':
                $query->where($column, 'LIKE', "%{$value}%");
                break;
            case 'not_contains':
                $query->where($column, 'NOT LIKE', "%{$value}%");
                break;
            case 'starts_with':
                $query->where($column, 'LIKE', "{$value}%");
                break;
            case 'ends_with':
                $query->where($column, 'LIKE', "%{$value}");
                break;
            case 'greater_than':
                $query->where($column, '>', $value);
                break;
            case 'greater_than_or_equal':
                $query->where($column, '>=', $value);
                break;
            case 'less_than':
                $query->where($column, '<', $value);
                break;
            case 'less_than_or_equal':
                $query->where($column, '<=', $value);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($column, [$value[0], $value[1]]);
                }
                break;
            case 'in':
                $list = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
                $query->whereIn($column, $list);
                break;
            case 'not_in':
                $list = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
                $query->whereNotIn($column, $list);
                break;
            case 'is_null':
                $query->whereNull($column);
                break;
            case 'is_not_null':
                $query->whereNotNull($column);
                break;

            // Operadores Temporais
            case 'today':
                $query->whereDate($column, '=', Carbon::now()->toDateString());
                break;
            case 'yesterday':
                $query->whereDate($column, '=', Carbon::now()->subDay()->toDateString());
                break;
            case 'last_n_days':
                $days = max(1, (int) $value);
                $query->where($column, '>=', Carbon::now()->subDays($days));
                break;
            case 'last_n_hours':
                $hours = max(1, (int) $value);
                $query->where($column, '>=', Carbon::now()->subHours($hours));
                break;
            case 'last_n_weeks':
                $weeks = max(1, (int) $value);
                $query->where($column, '>=', Carbon::now()->subWeeks($weeks));
                break;
            case 'last_n_months':
                $months = max(1, (int) $value);
                $query->where($column, '>=', Carbon::now()->subMonths($months));
                break;
            case 'before':
                $query->where($column, '<', Carbon::parse($value));
                break;
            case 'after':
                $query->where($column, '>', Carbon::parse($value));
                break;
            case 'between_dates':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($column, [Carbon::parse($value[0]), Carbon::parse($value[1])]);
                }
                break;
        }
    }

    protected function applyRawComparison(Builder $query, string $expression, string $operator, float|int $value, array $bindings): void
    {
        $sqlOp = match ($operator) {
            'greater_than' => '>',
            'greater_than_or_equal' => '>=',
            'less_than' => '<',
            'less_than_or_equal' => '<=',
            'not_equals' => '!=',
            default => '=',
        };

        $bindings[] = $value;
        $query->whereRaw("{$expression} {$sqlOp} CAST(? AS NUMERIC)", $bindings);
    }

    protected function applyRawTemporalComparison(Builder $query, string $expression, string $operator, mixed $value, array $bindings): void
    {
        switch ($operator) {
            case 'is_null':
                $query->whereRaw("{$expression} IS NULL", $bindings);
                break;
            case 'is_not_null':
                $query->whereRaw("{$expression} IS NOT NULL", $bindings);
                break;
            case 'last_n_days':
                $days = max(1, (int) $value);
                $date = Carbon::now('UTC')->subDays($days)->toDateTimeString();
                $bindings[] = $date;
                $query->whereRaw("{$expression} >= ?", $bindings);
                break;
            case 'before':
                $bindings[] = Carbon::parse($value)->toDateTimeString();
                $query->whereRaw("{$expression} < ?", $bindings);
                break;
            case 'after':
                $bindings[] = Carbon::parse($value)->toDateTimeString();
                $query->whereRaw("{$expression} > ?", $bindings);
                break;
        }
    }
}
