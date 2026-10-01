<?php

namespace App\Services\Templates;

class TemplateVariableRegistry
{
    /**
     * Catálogo mestre de variáveis dinâmicas disponíveis para templates.
     */
    public static function all(): array
    {
        return [
            // Categoria: DADOS DO JOGADOR (PLAYER)
            'player.name' => [
                'key' => 'player.name',
                'label' => 'Nome Completo',
                'category' => 'PLAYER',
                'type' => 'string',
                'required' => false,
                'example' => 'Carlos Eduardo Santos',
                'description' => 'Nome completo cadastrado pelo apostador.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.first_name' => [
                'key' => 'player.first_name',
                'label' => 'Primeiro Nome',
                'category' => 'PLAYER',
                'type' => 'string',
                'required' => false,
                'example' => 'Carlos',
                'description' => 'Primeiro nome extraído do cadastro do apostador.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.email' => [
                'key' => 'player.email',
                'label' => 'E-mail',
                'category' => 'PLAYER',
                'type' => 'string',
                'required' => false,
                'example' => 'carlos.santos@email.com',
                'description' => 'Endereço de e-mail do apostador.',
                'channels' => ['EMAIL'],
            ],
            'player.phone' => [
                'key' => 'player.phone',
                'label' => 'Telefone / WhatsApp',
                'category' => 'PLAYER',
                'type' => 'string',
                'required' => false,
                'example' => '5531998877661',
                'description' => 'Número de telefone normalizado.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.city' => [
                'key' => 'player.city',
                'label' => 'Cidade',
                'category' => 'PLAYER',
                'type' => 'string',
                'required' => false,
                'example' => 'Belo Horizonte',
                'description' => 'Cidade de residência do apostador.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.state' => [
                'key' => 'player.state',
                'label' => 'Estado (UF)',
                'category' => 'PLAYER',
                'type' => 'string',
                'required' => false,
                'example' => 'MG',
                'description' => 'Sigla do estado da federação.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.birth_date' => [
                'key' => 'player.birth_date',
                'label' => 'Data de Nascimento',
                'category' => 'PLAYER',
                'type' => 'date',
                'required' => false,
                'example' => '12/04/1988',
                'description' => 'Data de nascimento formatada (DD/MM/YYYY).',
                'channels' => ['EMAIL'],
            ],
            'player.created_at' => [
                'key' => 'player.created_at',
                'label' => 'Data de Cadastro',
                'category' => 'PLAYER',
                'type' => 'date',
                'required' => false,
                'example' => '01/01/2026',
                'description' => 'Data em que o jogador foi registrado.',
                'channels' => ['EMAIL', 'SMS'],
            ],

            // Categoria: CONTA (ACCOUNT)
            'player.external_id' => [
                'key' => 'player.external_id',
                'label' => 'ID Externo da Conta',
                'category' => 'ACCOUNT',
                'type' => 'string',
                'required' => false,
                'example' => 'PLY-1001',
                'description' => 'Identificador único do jogador na plataforma de apostas.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.status' => [
                'key' => 'player.status',
                'label' => 'Status da Conta',
                'category' => 'ACCOUNT',
                'type' => 'string',
                'required' => false,
                'example' => 'ACTIVE',
                'description' => 'Status da conta (ACTIVE, INACTIVE, etc.).',
                'channels' => ['EMAIL'],
            ],
            'player.affiliate' => [
                'key' => 'player.affiliate',
                'label' => 'Código de Afiliado',
                'category' => 'ACCOUNT',
                'type' => 'string',
                'required' => false,
                'example' => 'afiliado_top_br',
                'description' => 'Canal ou código de afiliação do jogador.',
                'channels' => ['EMAIL', 'SMS'],
            ],

            // Categoria: MÉTRICAS FINANCEIRAS (FINANCIAL)
            'player.deposit.total' => [
                'key' => 'player.deposit.total',
                'label' => 'Total Depositado (R$)',
                'category' => 'FINANCIAL',
                'type' => 'currency',
                'required' => false,
                'example' => 'R$ 1.500,00',
                'description' => 'Volume total de depósitos confirmados.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.deposit.count' => [
                'key' => 'player.deposit.count',
                'label' => 'Qtd. de Depósitos',
                'category' => 'FINANCIAL',
                'type' => 'number',
                'required' => false,
                'example' => '5',
                'description' => 'Quantidade total de depósitos realizados.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.deposit.last_amount' => [
                'key' => 'player.deposit.last_amount',
                'label' => 'Valor do Último Depósito',
                'category' => 'FINANCIAL',
                'type' => 'currency',
                'required' => false,
                'example' => 'R$ 150,00',
                'description' => 'Quantia do depósito mais recente.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.bet.total' => [
                'key' => 'player.bet.total',
                'label' => 'Total Apostado (R$)',
                'category' => 'FINANCIAL',
                'type' => 'currency',
                'required' => false,
                'example' => 'R$ 3.200,00',
                'description' => 'Volume acumulado em apostas.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.bet.count' => [
                'key' => 'player.bet.count',
                'label' => 'Qtd. de Apostas',
                'category' => 'FINANCIAL',
                'type' => 'number',
                'required' => false,
                'example' => '42',
                'description' => 'Número de bilhetes ou rodadas de cassino.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'player.bet.last_amount' => [
                'key' => 'player.bet.last_amount',
                'label' => 'Valor da Última Aposta',
                'category' => 'FINANCIAL',
                'type' => 'currency',
                'required' => false,
                'example' => 'R$ 50,00',
                'description' => 'Valor da aposta mais recente.',
                'channels' => ['EMAIL', 'SMS'],
            ],

            // Categoria: SEGMENTAÇÃO (SEGMENTATION)
            'segment.name' => [
                'key' => 'segment.name',
                'label' => 'Nome do Segmento',
                'category' => 'SEGMENTATION',
                'type' => 'string',
                'required' => false,
                'example' => 'VIPs Ativos',
                'description' => 'Nome do segmento associado à comunicação.',
                'channels' => ['EMAIL', 'SMS'],
            ],

            // Categoria: PLATAFORMA (PLATFORM)
            'platform.name' => [
                'key' => 'platform.name',
                'label' => 'Nome da Plataforma',
                'category' => 'PLATFORM',
                'type' => 'string',
                'required' => false,
                'example' => 'Bet Brasil',
                'description' => 'Nome comercial da plataforma operadora.',
                'channels' => ['EMAIL', 'SMS'],
            ],
            'platform.slug' => [
                'key' => 'platform.slug',
                'label' => 'Slug da Plataforma',
                'category' => 'PLATFORM',
                'type' => 'string',
                'required' => false,
                'example' => 'bet-brasil',
                'description' => 'Identificador textual da plataforma.',
                'channels' => ['EMAIL', 'SMS'],
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists(trim($key), self::all());
    }

    public static function get(string $key): ?array
    {
        return self::all()[trim($key)] ?? null;
    }

    /**
     * Retorna variáveis agrupadas por categoria para a API e o seletor do Frontend.
     */
    public static function grouped(?string $channel = null): array
    {
        $all = self::all();
        $grouped = [];

        foreach ($all as $key => $meta) {
            if ($channel && !in_array(strtoupper($channel), $meta['channels'])) {
                continue;
            }

            $cat = $meta['category'] ?? 'GENERAL';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $meta;
        }

        return $grouped;
    }

    /**
     * Extrai todas as tags de variáveis do conteúdo (ex: {{player.name}} ou {{player.first_name|default:"Amigo"}}).
     * Retorna array com ['raw' => '{{...}}', 'key' => 'player.first_name', 'fallback' => 'Amigo'].
     */
    public static function extractVariables(?string $content): array
    {
        if (empty($content)) {
            return [];
        }

        $pattern = '/\{\{\s*([a-zA-Z0-9_\.]+)(?:\s*\|\s*default\s*:\s*["\']([^"\']*)["\'])?\s*\}\}/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $extracted = [];
        foreach ($matches as $m) {
            $extracted[] = [
                'raw' => $m[0],
                'key' => trim($m[1]),
                'fallback' => $m[2] ?? null,
            ];
        }

        return $extracted;
    }

    /**
     * Valida sintaxe e permissão de variáveis no conteúdo de um template para o canal específico.
     */
    public static function validateContentVariables(?string $content, string $channel): array
    {
        if (empty($content)) {
            return ['is_valid' => true, 'errors' => [], 'variables' => []];
        }

        $errors = [];
        $variables = [];
        $upperChannel = strtoupper($channel);

        // 1. Detectar tags malformadas (ex: {player.name} com apenas 1 chave ou chaves abertas sem fechar)
        if (preg_match('/(?<!\{)\{(?!\{)\s*(player|segment|platform)\.[^}]*?(?<!\})\}(?!\})/', $content, $bad)) {
            $errors[] = "Tag malformada detectada: '{$bad[0]}'. Utilize chave dupla {{...}}.";
        }

        $extracted = self::extractVariables($content);
        foreach ($extracted as $item) {
            $key = $item['key'];
            $variables[] = $key;

            if (!self::has($key)) {
                $errors[] = "A variável '{{{$key}}}' não existe no catálogo oficial de variáveis.";
                continue;
            }

            $meta = self::get($key);
            if (!in_array($upperChannel, $meta['channels'])) {
                $errors[] = "A variável '{{{$key}}}' não é compatível com o canal {$upperChannel}.";
            }
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
            'variables' => array_values(array_unique($variables)),
        ];
    }
}
