# BET CRM — ANALYTICS, DASHBOARDS E INTELIGÊNCIA OPERACIONAL

## 1. Visão Geral da Arquitetura Analítica

O módulo de **Analytics, Dashboard e Inteligência Operacional** do BET CRM centraliza e correlaciona todos os eventos transacionais, cadastrais e de mensageria gerados pelas plataformas de apostas.

A camada é 100% orientada a **dados reais do banco**, sem fabricação de métricas fictícias. Quando não há dados suficientes para um período ou segmento selecionado, a plataforma retorna explicitamente o estado correspondente (*"Sem dados suficientes para o período selecionado"*).

```text
EVENTOS TRANSACIONAIS (Depósitos, Apostas, Saques, Logins)
MENSAGENS & TRACKING (Envios, Entregas, Aberturas, Cliques, Bounces)
JORNADAS & AUTOMAÇÕES (Gatilhos, Nós, Passos, Conversões)
TITULARES LGPD (Consentimentos, DSRs, Anonimizações)
                      │
                      ▼
     CAMADA DE NORMALIZAÇÃO & DOMÍNIO
     (PeriodService, PlayerRiskService, ChurnService)
                      │
                      ▼
       REDIS CACHE MULTI-TENANT
  Key Pattern: betcrm:analytics:{platformId}:{metric}:{hash}
                      │
                      ▼
 API REST v1 (/api/v1/analytics/...) & DASHBOARDS NEXT.JS 15
```

---

## 2. Camada de Períodos e Variação Temporal (`PeriodService`)

O `PeriodService` padroniza os filtros temporais para todo o sistema analítico:

* **Períodos Suportados:**
  * `today`: Início do dia atual (00:00:00) até o instante presente.
  * `yesterday`: 00:00:00 até 23:59:59 do dia anterior.
  * `7d`: Últimos 7 dias completos.
  * `30d`: Últimos 30 dias completos (padrão).
  * `90d`: Últimos 90 dias completos.
  * `this_month`: Do 1º dia do mês corrente até hoje.
  * `last_month`: Mês calendário imediatamente anterior completo.
  * `custom`: Intervalo arbitrário delimitado por `date_from` e `date_to`.

* **Cálculo de Período Anterior:**
  * A duração do período atual é calculada em segundos (`$durationSeconds = $end->diffInSeconds($start)`).
  * O período anterior equivalente é calculado com a mesma duração terminando imediatamente antes de `$start`.

* **Proteção contra Divisão por Zero:**
  * Toda variação percentual é processada por `calculatePercentageChange($current, $prior)`.
  * Se ambos forem 0: variação `0.0%`.
  * Se o período anterior for 0 e o atual > 0: variação `+100.0%`.
  * Se o período anterior for 0 e o atual < 0: variação `-100.0%`.

---

## 3. Classificação de Risco e Análise de Churn

### 3.1 Classificação Operacional de Risco (`PlayerRiskService`)
A base de jogadores é categorizada em 4 perfis operacionais com base na recência de atividade e login:

1. **ATIVO**: Última atividade ou login nos últimos 7 dias.
2. **ATENÇÃO**: Última atividade ou login entre 8 e 14 dias atrás.
3. **RISCO**: Última atividade ou login entre 15 e 30 dias atrás.
4. **INATIVO**: Sem atividade nem login há mais de 30 dias (ou cadastro sem histórico).

### 3.2 Análise de Churn Dinâmica (`ChurnService`)
Permite simular o impacto de diferentes limites de inatividade:
* Limites suportados: `7`, `14`, `30` (padrão), `60` e `90` dias.
* Métricas retornadas:
  * `total_players`: Total da base na plataforma.
  * `churned_players`: Jogadores inativos além do limite.
  * `at_risk_players`: Jogadores na faixa de aviso (entre metade do limite e o limite).
  * `active_players`: Jogadores engajados.
  * `churn_rate`: Percentual de churn calculado sobre a base real.

---

## 4. Retenção e Análise de Cohorts (`PlayerAnalyticsService`)

A análise de retenção processa matrizes semanais de novos jogadores contra eventos subsequentes:
* **Matriz de Cohort:** Avalia a retenção nos marcos **D1, D7, D14, D30, D60 e D90** a partir da semana de registro.
* **Curva Média de Retenção:** Gera o percentual médio ponderado de retorno da plataforma ao longo dos 90 dias pós-cadastro.

---

## 5. Inteligência Financeira e Apostas

### 5.1 Métricas Financeiras (`FinancialAnalyticsService`)
Extraídas diretamente dos eventos transacionais normalizados (`events`):
* `total_deposit_amount` e `deposit_count`: Volume e contagem de depósitos confirmados.
* `average_deposit_ticket`: Ticket médio por depósito.
* `total_withdrawal_amount` e `withdrawal_count`: Volume e contagem de saques homologados.
* `net_revenue` (Saldo Operacional Líquido): `Depósitos - Saques`.
* `first_time_depositors` (FTDs): Jogadores que realizaram seu primeiro depósito histórico no período.
* `recurring_depositors`: Depositantes recorrentes.

### 5.2 Atividade de Apostas (`BettingAnalyticsService`)
* Analisa eventos `BET_PLACED` e `BET_SETTLED`.
* Métricas: `turnover` (volume apostado), `total_bets`, `total_won`, `total_lost`, `win_rate`.
* Quando não houver apostas registradas, retorna `has_data: false` com mensagem operacional amigável.

---

## 6. Funis de Conversão e Métricas de Marketing

O `MarketingAnalyticsService` consolida o funil de conversão em 6 estágios:

```text
1. AUDIÊNCIA (Total elegível nos segmentos)
   ↓
2. ENVIADO (Mensagens despachadas aos provedores)
   ↓
3. ENTREGUE (Confirmações de entrega recebidas via Webhook/DLR)
   ↓
4. ABERTO (Tracking pixels de abertura registrados)
   ↓
5. CLIQUE (Tracking de links validados)
   ↓
6. CONVERSÃO (Metas ou depósitos atribuídos à campanha)
```

Adicionalmente, apresenta:
* Desempenho comparativo por canal (E-mail vs SMS).
* Desempenho comparativo de templates (Taxa de abertura, CTR, volume).
* Ranking de campanhas mais eficientes.

---

## 7. Cache Inteligente e Isolamento Multi-Tenant

* O serviço `AnalyticsCacheService` armazena respostas analíticas no Redis.
* **Chave Isolada por Plataforma:**
  `betcrm:analytics:{platformId}:{metric}:{sha256_filters}`
* **Invalidação Forçada:**
  `POST /api/v1/analytics/cache/invalidate` permite que operadores forcem a limpeza do cache de sua plataforma ativa sem interferir nas demais plataformas.

---

## 8. Catálogo de Endpoints REST

| Método | Endpoint | Permissão RBAC | Descrição |
|---|---|---|---|
| `GET` | `/api/v1/analytics/dashboard` | `analytics.view` | Dashboard executivo consolidado |
| `GET` | `/api/v1/analytics/players` | `analytics.view` ou `analytics.players` | Analítica de jogadores e evolução |
| `GET` | `/api/v1/analytics/retention` | `analytics.view` ou `analytics.players` | Matriz de cohort D1 a D90 |
| `GET` | `/api/v1/analytics/churn` | `analytics.view` ou `analytics.players` | Métricas de churn com filtro configurável |
| `GET` | `/api/v1/analytics/finance` | `analytics.view` ou `analytics.finance` | Depósitos, saques, GGR e FTDs |
| `GET` | `/api/v1/analytics/betting` | `analytics.view` ou `analytics.betting` | Turnover e win rate de apostas |
| `GET` | `/api/v1/analytics/marketing` | `analytics.view` ou `analytics.marketing` | Funil de marketing e canais |
| `GET` | `/api/v1/analytics/templates` | `analytics.view` ou `analytics.marketing` | Comparação de performance de templates |
| `GET` | `/api/v1/analytics/providers` | `analytics.view` ou `analytics.providers` | Saúde, latência e circuit breaker |
| `GET` | `/api/v1/analytics/automations` | `analytics.view` ou `analytics.automations`| Execuções e conversão de jornadas |
| `GET` | `/api/v1/analytics/privacy` | `analytics.view` ou `privacy.view` | Indicadores LGPD e conformidade |
| `GET` | `/api/v1/analytics/segments` | `analytics.view` ou `segments.view` | Tamanho e uso de segmentos |
| `POST`| `/api/v1/analytics/cache/invalidate`| `analytics.view` | Limpeza forçada de cache da plataforma |
