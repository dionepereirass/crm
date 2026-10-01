# BET CRM — Motor de Segmentação Dinâmica & Filtros Avançados

Este documento detalha a arquitetura, especificação de dados, catálogo de operadores e estratégias de execução do **Motor de Segmentação Dinâmica** do **BET CRM**, implementado na **FASE 5**.

---

## 1. Visão Geral da Arquitetura

O motor de segmentação do BET CRM foi desenhado para transformar árvores abstratas de sintaxe de regras (AST em formato JSONB) em consultas SQL parametrizadas de alta performance sobre o PostgreSQL (e SQLite em testes locais), com estrito isolamento por plataforma (`platform_id`), caching em camadas no Redis e auditoria granular.

```
                  +-----------------------------------+
                  |  Visual Segment Builder (Next.js) |
                  +-----------------+-----------------+
                                    | JSON AST
                                    v
                  +-----------------------------------+
                  |   REST API: /api/v1/segments      |
                  |  (Sanctum + TenantPlatformContext)|
                  +-----------------+-----------------+
                                    |
                                    v
                  +-----------------------------------+
                  |     SegmentQueryCompiler (AST)    |
                  |  - SegmentFieldRegistry           |
                  |  - SegmentOperatorRegistry        |
                  +--------+-----------------+--------+
                           |                 |
         +-----------------+                 +-----------------+
         v                                                     v
+------------------------+                         +------------------------+
|  PostgreSQL / DB Query |                         |    Redis Cache Layer   |
|  - Correlated Aggregates                         |  - Key: betcrm:segment |
|  - JSON Extraction                               |  - TTL: 3600s          |
|  - Parameter Bindings                            |  - Event Invalidation  |
+------------------------+                         +------------------------+
```

---

## 2. Estrutura do AST de Regras (`rules_tree`)

O modelo de dados armazena as condições na coluna `rules_tree` (JSONB) da tabela `segments`. O nó raiz e os nós intermediários são grupos lógicos (`operator: "AND" | "OR"`), e os nós folha são condições atômicas (`type: "condition"`).

### 2.1 Exemplo de AST Aninhada
```json
{
  "operator": "AND",
  "children": [
    {
      "type": "condition",
      "field": "player.status",
      "operator": "equals",
      "value": "ACTIVE"
    },
    {
      "type": "group",
      "operator": "OR",
      "children": [
        {
          "type": "condition",
          "field": "player.state",
          "operator": "equals",
          "value": "SP"
        },
        {
          "type": "condition",
          "field": "deposit.total",
          "operator": "greater_than",
          "value": 500,
          "period_value": 30,
          "period_unit": "days"
        }
      ]
    }
  ]
}
```

---

## 3. Catálogo de Campos (`SegmentFieldRegistry`)

O catálogo categoriza os campos permitidos para garantir integridade e prevenir injeções de SQL.

| Categoria | Chave de Campo | Tipo de Dado | Operadores Suportados | Descrição |
| :--- | :--- | :--- | :--- | :--- |
| **Jogador** | `player.name` | STRING | equals, contains, starts_with | Nome cadastral |
| **Jogador** | `player.email` | STRING | equals, contains, ends_with | E-mail do apostador |
| **Jogador** | `player.phone` | STRING | equals, starts_with, is_null | Telefone / WhatsApp |
| **Jogador** | `player.cpf` | STRING | equals, is_null, is_not_null | Documento fiscal |
| **Jogador** | `player.birth_date` | DATE | equals, before, after, between | Data de nascimento |
| **Jogador** | `player.gender` | ENUM | equals, in, not_in | M, F, OTHER |
| **Jogador** | `player.state` | STRING | equals, in, not_in | Estado da federação (UF) |
| **Jogador** | `player.city` | STRING | equals, contains, in | Cidade de residência |
| **Jogador** | `player.status` | ENUM | equals, in, not_in | ACTIVE, INACTIVE, BLOCKED |
| **Tags** | `player.has_tag` | STRING / INT | equals, contains | Vínculo de Tag (Nome ou ID) |
| **Tags** | `player.not_has_tag` | STRING / INT | equals, contains | Não possui Tag vinculada |
| **Consentimentos** | `consent.marketing_email` | BOOLEAN | equals | Consentimento LGPD E-mail |
| **Consentimentos** | `consent.marketing_sms` | BOOLEAN | equals | Consentimento LGPD SMS |
| **Consentimentos** | `consent.marketing_whatsapp`| BOOLEAN | equals | Consentimento WhatsApp |
| **Depósitos** | `deposit.total` | DECIMAL | >, >=, <, <=, between | Soma de depósitos confirmados |
| **Depósitos** | `deposit.count` | INTEGER | >, >=, <, <=, between | Quantidade de depósitos |
| **Depósitos** | `deposit.last_amount` | DECIMAL | >, >=, <, <=, between | Valor do último depósito |
| **Depósitos** | `deposit.last_at` | DATETIME | today, yesterday, last_n_days | Data do último depósito |
| **Apostas** | `bet.total` | DECIMAL | >, >=, <, <=, between | Soma do volume apostado |
| **Apostas** | `bet.count` | INTEGER | >, >=, <, <=, between | Quantidade de bilhetes/rodadas |
| **Apostas** | `bet.last_at` | DATETIME | today, yesterday, last_n_days | Data da última aposta |
| **Saques** | `withdrawal.total` | DECIMAL | >, >=, <, <=, between | Soma de saques concluídos |
| **Saques** | `withdrawal.count` | INTEGER | >, >=, <, <=, between | Quantidade de saques |
| **Saques** | `withdrawal.last_at` | DATETIME | today, yesterday, last_n_days | Data do último saque |
| **Sessão & Login** | `login.count` | INTEGER | >, >=, <, <=, between | Quantidade de autenticações |
| **Sessão & Login** | `login.last_at` | DATETIME | today, yesterday, last_n_days | Data do último login |
| **Eventos** | `event.occurred` | EVENT | exists, not_exists, equals | Ocorrência de evento canônico |

---

## 4. Catálogo de Operadores (`SegmentOperatorRegistry`)

1. **Comparação Básica & Textual:** `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`, `ends_with`.
2. **Comparação Numérica & Listas:** `greater_than`, `greater_than_or_equal`, `less_than`, `less_than_or_equal`, `between`, `in`, `not_in`.
3. **Nulidade:** `is_null`, `is_not_null`.
4. **Janelas Temporais:** `today`, `yesterday`, `last_n_days`, `last_n_hours`, `last_n_weeks`, `last_n_months`, `before`, `after`, `between_dates`.
5. **Existência:** `exists`, `not_exists`.

---

## 5. Compilação de Agregados Financeiros e Eventos

Para métricas financeiras (ex: `deposit.total`), o `SegmentQueryCompiler` monta subqueries correlacionadas filtradas por `e.processing_status = 'PROCESSED'`, `e.platform_id = ?`, `et.key = ?` e janela temporal móvel.

### SQL Gerado (Exemplo PostgreSQL):
```sql
SELECT * FROM "players"
WHERE "platform_id" = $1
  AND (
    (SELECT COALESCE(SUM(CAST(e.normalized_payload->'data'->>'amount' AS numeric)), 0)
     FROM events e
     INNER JOIN event_types et ON et.id = e.event_type_id
     WHERE e.player_id = players.id
       AND e.platform_id = $2
       AND et.key = 'DEPOSIT_SUCCESS'
       AND e.processing_status = 'PROCESSED'
       AND e.occurred_at >= $3) > CAST($4 AS NUMERIC)
  )
```

> **Precisão Numérica & Compatibilidade:** Para garantir consistência em SQLite e PostgreSQL, os parâmetros de comparação são explicitamente convertidos através de `CAST(? AS NUMERIC)`, eliminando inconsistências de afinidade de tipos do driver PDO.

---

## 6. Estratégia de Caching no Redis & Invalidação

1. **Estrutura de Chave:**
   `betcrm:segment:{platform_id}:{segment_id}:count`
2. **TTL Padrão:** 3.600 segundos (1 hora).
3. **Pattern Remember:** Se a chave existir no Redis, a contagem é servida em < 2ms sem tocar no banco relacional.
4. **Invalidação Inteligente por Eventos (`invalidateForEvent`):**
   - Ao receber `DEPOSIT_SUCCESS`, todos os segmentos ativos da plataforma com regras em `deposit.` têm o cache invalidado.
   - Ao receber `BET_PLACED` / `BET_SETTLED`, segmentos com `bet.` são invalidados.
   - Ao receber `WITHDRAWAL_SUCCESS`, segmentos com `withdrawal.` são invalidados.
5. **Endpoint Manual de Refresh:** `POST /api/v1/segments/{id}/refresh` força a recompilação e sincronização do cache Redis e do modelo no banco.

---

## 7. Endpoints da API REST

| Método | Rota | Descrição | Permissão |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/segments` | Listagem paginada com busca e filtro por status | `segments.view` |
| `POST` | `/api/v1/segments` | Criação de novo segmento com AST de regras | `segments.create` |
| `GET` | `/api/v1/segments/{id}` | Detalhes do segmento e regras | `segments.view` |
| `PUT` | `/api/v1/segments/{id}` | Atualização de metadados ou regras | `segments.update` |
| `DELETE`| `/api/v1/segments/{id}` | Soft delete do segmento | `segments.delete` |
| `POST` | `/api/v1/segments/{id}/activate` | Ativação do segmento | `segments.activate` |
| `POST` | `/api/v1/segments/{id}/deactivate` | Desativação do segmento | `segments.activate` |
| `POST` | `/api/v1/segments/{id}/duplicate` | Duplicação do segmento como rascunho | `segments.create` |
| `POST` | `/api/v1/segments/preview` | Preview dinâmico de regras (sem persistir) | `segments.preview` |
| `GET` | `/api/v1/segments/{id}/preview` | Preview de segmento existente | `segments.view` |
| `POST` | `/api/v1/segments/{id}/refresh` | Recálculo de membros e renovação de cache | `segments.refresh` |
| `GET` | `/api/v1/segments/{id}/members` | Lista paginada dos jogadores pertencentes | `segments.members` |
| `GET` | `/api/v1/segment-fields` | Catálogo de campos disponíveis | `auth:sanctum` |
| `GET` | `/api/v1/segment-operators` | Catálogo de operadores suportados | `auth:sanctum` |
