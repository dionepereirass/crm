<?php

namespace App\Services\Segments;

class SegmentFieldRegistry
{
    public static function all(): array
    {
        return [
            // Categoria: DADOS DO JOGADOR (PLAYERS)
            'player.name' => [
                'label' => 'Nome do Jogador',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with'],
            ],
            'player.email' => [
                'label' => 'E-mail do Jogador',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with'],
            ],
            'player.phone' => [
                'label' => 'Telefone do Jogador',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'is_null', 'is_not_null'],
            ],
            'player.cpf' => [
                'label' => 'CPF',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'is_null', 'is_not_null'],
            ],
            'player.birth_date' => [
                'label' => 'Data de Nascimento',
                'category' => 'Jogador',
                'type' => 'DATE',
                'operators' => ['equals', 'before', 'after', 'between_dates', 'is_null', 'is_not_null'],
            ],
            'player.gender' => [
                'label' => 'Gênero',
                'category' => 'Jogador',
                'type' => 'ENUM',
                'options' => ['M' => 'Masculino', 'F' => 'Feminino', 'OTHER' => 'Outro'],
                'operators' => ['equals', 'not_equals', 'in', 'not_in'],
            ],
            'player.state' => [
                'label' => 'Estado (UF)',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'in', 'not_in', 'is_null', 'is_not_null'],
            ],
            'player.city' => [
                'label' => 'Cidade',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'contains', 'in', 'not_in'],
            ],
            'player.status' => [
                'label' => 'Status do Jogador',
                'category' => 'Jogador',
                'type' => 'ENUM',
                'options' => [
                    'active' => 'Ativo',
                    'inactive' => 'Inativo',
                    'churned' => 'Churned',
                    'blocked' => 'Bloqueado',
                    'pending' => 'Pendente',
                ],
                'operators' => ['equals', 'not_equals', 'in', 'not_in'],
            ],
            'player.affiliate' => [
                'label' => 'Afiliado / Origem de Aquisição',
                'category' => 'Jogador',
                'type' => 'STRING',
                'operators' => ['equals', 'not_equals', 'contains', 'is_null', 'is_not_null'],
            ],
            'player.created_at' => [
                'label' => 'Data de Cadastro',
                'category' => 'Jogador',
                'type' => 'DATETIME',
                'operators' => ['today', 'yesterday', 'last_n_days', 'last_n_weeks', 'last_n_months', 'before', 'after', 'between_dates'],
            ],
            'player.last_login_at' => [
                'label' => 'Último Login',
                'category' => 'Jogador',
                'type' => 'DATETIME',
                'operators' => ['today', 'yesterday', 'last_n_days', 'last_n_hours', 'last_n_weeks', 'last_n_months', 'before', 'after', 'between_dates', 'is_null', 'is_not_null'],
            ],

            // Categoria: TAGS
            'player.has_tag' => [
                'label' => 'Possui a Tag',
                'category' => 'Tags',
                'type' => 'TAG',
                'operators' => ['equals', 'in'],
            ],
            'player.not_has_tag' => [
                'label' => 'Não Possui a Tag',
                'category' => 'Tags',
                'type' => 'TAG',
                'operators' => ['equals', 'in'],
            ],

            // Categoria: CONSENTIMENTOS LGPD
            'consent.marketing' => [
                'label' => 'Consentimento de Marketing (Opt-in)',
                'category' => 'Consentimento LGPD',
                'type' => 'BOOLEAN',
                'operators' => ['equals'],
            ],
            'consent.transactional' => [
                'label' => 'Consentimento Transacional',
                'category' => 'Consentimento LGPD',
                'type' => 'BOOLEAN',
                'operators' => ['equals'],
            ],

            // Categoria: DEPÓSITOS (FINANCEIRO & TRANSAÇÕES)
            'deposit.count' => [
                'label' => 'Quantidade de Depósitos',
                'category' => 'Depósitos',
                'type' => 'INTEGER',
                'supports_period' => true,
                'operators' => ['equals', 'not_equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'deposit.total' => [
                'label' => 'Valor Total Depositado (R$)',
                'category' => 'Depósitos',
                'type' => 'DECIMAL',
                'supports_period' => true,
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between', 'equals'],
            ],
            'deposit.average' => [
                'label' => 'Ticket Médio de Depósito (R$)',
                'category' => 'Depósitos',
                'type' => 'DECIMAL',
                'supports_period' => true,
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'deposit.last_amount' => [
                'label' => 'Valor do Último Depósito (R$)',
                'category' => 'Depósitos',
                'type' => 'DECIMAL',
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'deposit.last_at' => [
                'label' => 'Data do Último Depósito',
                'category' => 'Depósitos',
                'type' => 'DATETIME',
                'operators' => ['today', 'yesterday', 'last_n_days', 'last_n_hours', 'last_n_weeks', 'last_n_months', 'before', 'after', 'is_null', 'is_not_null'],
            ],

            // Categoria: APOSTAS (BETS)
            'bet.count' => [
                'label' => 'Quantidade de Apostas',
                'category' => 'Apostas',
                'type' => 'INTEGER',
                'supports_period' => true,
                'operators' => ['equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'bet.total' => [
                'label' => 'Volume Total Apostado (R$)',
                'category' => 'Apostas',
                'type' => 'DECIMAL',
                'supports_period' => true,
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'bet.average' => [
                'label' => 'Média de Valor por Aposta (R$)',
                'category' => 'Apostas',
                'type' => 'DECIMAL',
                'supports_period' => true,
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'bet.last_amount' => [
                'label' => 'Valor da Última Aposta (R$)',
                'category' => 'Apostas',
                'type' => 'DECIMAL',
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'bet.last_at' => [
                'label' => 'Data da Última Aposta',
                'category' => 'Apostas',
                'type' => 'DATETIME',
                'operators' => ['today', 'yesterday', 'last_n_days', 'last_n_hours', 'last_n_weeks', 'last_n_months', 'before', 'after', 'is_null', 'is_not_null'],
            ],

            // Categoria: SAQUES (WITHDRAWALS)
            'withdrawal.count' => [
                'label' => 'Quantidade de Saques',
                'category' => 'Saques',
                'type' => 'INTEGER',
                'supports_period' => true,
                'operators' => ['equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'withdrawal.total' => [
                'label' => 'Valor Total Sacado (R$)',
                'category' => 'Saques',
                'type' => 'DECIMAL',
                'supports_period' => true,
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'withdrawal.last_amount' => [
                'label' => 'Valor do Último Saque (R$)',
                'category' => 'Saques',
                'type' => 'DECIMAL',
                'operators' => ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'withdrawal.last_at' => [
                'label' => 'Data do Último Saque',
                'category' => 'Saques',
                'type' => 'DATETIME',
                'operators' => ['today', 'yesterday', 'last_n_days', 'last_n_hours', 'last_n_weeks', 'last_n_months', 'before', 'after', 'is_null', 'is_not_null'],
            ],

            // Categoria: SESSÃO & LOGIN
            'login.count' => [
                'label' => 'Quantidade de Logins',
                'category' => 'Sessão & Login',
                'type' => 'INTEGER',
                'supports_period' => true,
                'operators' => ['equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
            ],
            'login.last_at' => [
                'label' => 'Data do Último Login',
                'category' => 'Sessão & Login',
                'type' => 'DATETIME',
                'operators' => ['today', 'yesterday', 'last_n_days', 'last_n_hours', 'last_n_weeks', 'last_n_months', 'before', 'after', 'is_null', 'is_not_null'],
            ],

            // Categoria: EVENTOS CANÔNICOS
            'event.occurred' => [
                'label' => 'Evento Ocorreu',
                'category' => 'Eventos',
                'type' => 'EVENT',
                'supports_period' => true,
                'options' => [
                    'PLAYER_CREATED' => 'Cadastro de Jogador',
                    'PLAYER_UPDATED' => 'Atualização de Jogador',
                    'DEPOSIT_SUCCESS' => 'Depósito Confirmado',
                    'BET_PLACED' => 'Aposta Realizada',
                    'BET_SETTLED' => 'Aposta Liquidada',
                    'WITHDRAWAL_SUCCESS' => 'Saque Concluído',
                    'LOGIN' => 'Login do Jogador',
                ],
                'operators' => ['exists', 'not_exists', 'equals'],
            ],
        ];
    }

    public static function normalize(string $field): string
    {
        $aliases = [
            'name' => 'player.name',
            'email' => 'player.email',
            'phone' => 'player.phone',
            'cpf' => 'player.cpf',
            'birth_date' => 'player.birth_date',
            'gender' => 'player.gender',
            'state' => 'player.state',
            'city' => 'player.city',
            'status' => 'player.status',
            'affiliate' => 'player.affiliate',
            'promo_code' => 'player.promo_code',
            'created_at' => 'player.created_at',
            'registered_at' => 'player.created_at',
            'last_login_at' => 'player.last_login_at',
            'has_tag' => 'player.has_tag',
            'tags.name' => 'player.has_tag',
            'tag_name' => 'player.has_tag',
            'tag' => 'player.has_tag',
            'not_has_tag' => 'player.not_has_tag',
        ];

        return $aliases[$field] ?? $field;
    }

    public static function has(string $field): bool
    {
        $normalized = self::normalize($field);
        return array_key_exists($normalized, self::all());
    }

    public static function get(string $field): ?array
    {
        $normalized = self::normalize($field);
        return self::all()[$normalized] ?? null;
    }

    public static function grouped(): array
    {
        $all = self::all();
        $grouped = [
            'player' => [],
            'tags' => [],
            'consents' => [],
            'deposits' => [],
            'bets' => [],
            'withdrawals' => [],
            'logins' => [],
            'events' => [],
        ];

        foreach ($all as $key => $item) {
            $item['key'] = $key;
            if (str_starts_with($key, 'player.')) {
                if (in_array($key, ['player.has_tag', 'player.not_has_tag'])) {
                    $grouped['tags'][$key] = $item;
                } else {
                    $grouped['player'][$key] = $item;
                }
            } elseif (str_starts_with($key, 'consent.')) {
                $grouped['consents'][$key] = $item;
            } elseif (str_starts_with($key, 'deposit.')) {
                $grouped['deposits'][$key] = $item;
            } elseif (str_starts_with($key, 'bet.')) {
                $grouped['bets'][$key] = $item;
            } elseif (str_starts_with($key, 'withdrawal.')) {
                $grouped['withdrawals'][$key] = $item;
            } elseif (str_starts_with($key, 'login.')) {
                $grouped['logins'][$key] = $item;
            } elseif (str_starts_with($key, 'event.')) {
                $grouped['events'][$key] = $item;
            }
        }

        return $grouped;
    }
}

