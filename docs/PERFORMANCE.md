# BET CRM — Arquitetura de Performance, Escalabilidade e Otimização

Este documento consolida todas as técnicas, índices, estratégias de cache, balanceamento de workers assíncronos e diretrizes de concorrência aplicadas na **Fase 14** para sustentar plataformas de apostas e iGaming de alto volume de eventos e milhões de jogadores.

---

## 1. Visão Geral da Arquitetura de Escalabilidade

O BET CRM foi desenhado para processamento horizontal desacoplado com latência previsível:

```text
[ Milhões de Eventos Externos / Apostadores ]
                  ↓
[ Ingestão Ultrarrápida ] (HTTP 202 Accepted em < 15ms via Redis Streams/Queues)
                  ↓
[ Particionamento Lógico e Índices Compostos ] (PostgreSQL 16)
                  ↓
[ Redis Cache Cluster ] (Dashboard, Segmentos, Plataformas e Métricas Pré-Calculadas)
                  ↓
[ Filas Especializadas no Laravel Horizon ] (Priorização Dinâmica por Tipo de Carga)
                  ↓
[ Processamento em Lotes por Cursor ] (chunkById / LazyCollection, Zero Memory Leak)
```

---

## 2. Índices de Banco de Dados de Alta Performance (`2026_10_01_000012`)

Para evitar Table Scans (Seq Scans) em bases com dezenas de milhões de linhas, foram adicionados índices compostos cobrindo os padrões de consulta mais exigentes:

| Tabela | Nome do Índice | Colunas Indexadas | Benefício / Caso de Uso |
| :--- | :--- | :--- | :--- |
| `messages` | `idx_messages_platform_status_created` | `(platform_id, status, created_at)` | Acelera filtragem por status e ordenação cronológica na API e dashboard. |
| `messages` | `idx_messages_campaign_status` | `(campaign_id, status)` | Agiliza agregação de contadores em tempo real para campanhas ativas. |
| `message_events`| `idx_message_events_message_type` | `(message_id, event_type)` | Elimina N+1 e acelera a contagem de aberturas e cliques únicos (`OPENED`, `CLICKED`). |
| `message_events`| `idx_message_events_type_created` | `(event_type, created_at)` | Permite gráficos de séries temporais de entrega em milissegundos. |
| `events` | `idx_events_platform_type_occurred` | `(platform_id, event_type_id, occurred_at)` | Viabiliza agregações financeiras e comportamentais instantâneas no portal de Analytics. |
| `players` | `idx_players_platform_status_created` | `(platform_id, status, created_at)` | Otimiza análise de coortes, taxas de retenção e churn de jogadores. |
| `campaign_recipients` | `idx_campaign_recipients_chunking` | `(campaign_id, status, id)` | Permite iteração de alta performance via `chunkById` durante despachos massivos. |
| `automation_runs` | `idx_auto_runs_platform_status_created` | `(platform_id, status, created_at)` | Otimiza a listagem e auditoria de jornadas ativas e históricas. |
| `automation_runs` | `idx_auto_runs_auto_status` | `(automation_id, status)` | Acelera contadores de nós e etapas em execução. |
| `audit_logs` | `idx_audit_logs_platform_action_created` | `(platform_id, action, created_at)` | Agiliza trilhas de auditoria para compliance LGPD. |

---

## 3. Estratégia de Caching Distribuído com Redis

### 3.1 Cache de Contexto de Plataforma (`TenantPlatformContext`)
- Toda requisição autenticada do CRM carrega a plataforma ativa via header `X-Platform-Id` ou `X-Platform-Slug`.
- O middleware armazena em cache o registro da plataforma por **1 hora** (`betcrm:platform:id:{id}` e `betcrm:platform:slug:{slug}`).
- **Invalidação Automática**: Hooks `saved` e `deleted` no modelo `Platform` purgam o cache instantaneamente se configurações forem alteradas.
- **Ganho**: Elimina **1 query ao banco por requisição HTTP**, economizando milhares de consultas por minuto.

### 3.2 Cache de Métricas Analíticas e Dashboards (`AnalyticsCacheService`)
- O dashboard executivo e os relatórios analíticos utilizam memoização com TTL adaptativo (180s a 300s).
- Chaves canônicas com hash de filtros (`betcrm:analytics:{platform_id}:{metric}:{hash}`).
- A invalidação seletiva é disparada pela chegada de novos lotes de eventos ou via endpoint manual de purge (`POST /api/v1/analytics/cache/invalidate`).

### 3.3 Cache de Contagem de Segmentos (`SegmentCacheService`)
- Contagens de audiência de regras complexas do AST são armazenadas em cache por **1 hora**.
- Invalidação orientada a eventos: se o sistema processa `DEPOSIT_SUCCESS`, somente segmentos com regras de depósito são invalidados seletivamente.

---

## 4. Otimização de Filas e Concorrência no Laravel Horizon

O arquivo `config/horizon.php` foi calibrado para eliminar filas bloqueadas e balancear automaticamente o throughput:

```php
'supervisor-1' => [
    'connection' => 'redis',
    'queue' => [
        // Alta prioridade / Baixa latência (Fast Lane)
        'webhooks',
        'events',
        'messages',
        'emails',
        'sms',
        // Cargas em lote / Processamento massivo (Batch Lane)
        'campaigns',
        'campaign_messages',
        'automations',
        'analytics',
        'reports',
        'default',
    ],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'maxProcesses' => 15,
    'memory' => 128,
    'timeout' => 90,
]
```

### Tempos Máximos de Espera por Fila (Waits):
- `webhooks`: 15s
- `events`: 30s
- `messages`: 30s
- `emails` / `sms`: 45s
- `campaigns` / `campaign_messages`: 60s
- `automations`: 60s
- `analytics`: 90s
- `reports`: 120s

---

## 5. Processamento em Lotes e Economia de Memória (Zero Memory Leaks)

- **`chunkById()`**: Em vez de carregar dezenas de milhares de registros na memória RAM usando `get()`, os despachos de campanhas (`DispatchCampaignJob` e `CreateCampaignMessagesJob`) e compiladores de segmentos operam em lotes delimitados pelo cursor de ID (lotes de 250 a 500 registros).
- **Verificação de Estado em Tempo Real**: Durante o processamento de lotes longos, o status da campanha é revalidado a cada lote. Se o operador clicar em **Pausar** ou **Cancelar**, o processamento é interrompido imediatamente sem desperdício de créditos nem envio indevido.
