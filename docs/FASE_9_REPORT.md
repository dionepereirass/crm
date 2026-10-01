# RELATÓRIO DE ENTREGA — FASE 9
## BET CRM — TRACKING, EVENTOS DE ENTREGA E ANALYTICS DE CAMPANHAS

---

### 1. Resumo da Fase Concluída

A **FASE 9 — TRACKING, EVENTOS DE ENTREGA E ANALYTICS DE CAMPANHAS** foi integralmente desenvolvida, testada e validada com **100% de sucesso**.

Esta fase fecha o ciclo operacional de ponta a ponta do BET CRM:
- As mensagens geradas por campanhas agora possuem instrumentação completa de rastreamento (pixel de abertura 1x1 transparente, reescrita de links para tracking de cliques com proteção contra Open Redirect e links de opt-out/unsubscribe em conformidade estrita com a LGPD).
- Os webhooks de provedores externos (`Brevo`, `Zenvia` e formato genérico) foram integrados a uma camada unificada de normalização (`ProviderEventNormalizer`) com idempotência rigorosa baseada em ID do provedor ou hash SHA-256 e máquina de precedência de status imutável.
- O pipeline de analytics é totalmente assíncrono através de jobs em fila dedicada (`analytics`) do Laravel Horizon, persistindo métricas agregadas na tabela `campaign_metrics`.
- O modelo analítico segue os princípios de **Event Sourcing**, permitindo reconstrução integral e instantânea das métricas a qualquer momento via CLI (`php artisan campaigns:rebuild-analytics`) ou API administrativa.
- O frontend Next.js 15 foi expandido com dashboards analíticos operacionais de alto nível (`/analytics`), visualização analítica 360° em cada campanha (`/campaigns/[id]` nas abas *Analytics & Funil* e *Eventos*) e timeline visual do ciclo de vida das mensagens com stepper interativo (`/messages/[id]`).

---

### 2. Arquitetura de Tracking e Eventos Implementada

O fluxo de dados da Fase 9 opera segundo uma arquitetura desacoplada e resiliente:

```text
CAMPANHA / MENSAGEM
       │
       ▼
MessageService::send()
       │
       ├─► TrackingProcessor::processEmail()
       │     ├─ Gera open_tracking_token (64 caracteres)
       │     ├─ Injeta 1x1 pixel: /api/v1/tracking/open/{token}
       │     ├─ Extrai tags <a> e persiste em message_links
       │     ├─ Reescreve href para: /api/v1/tracking/click/{link_token}
       │     └─ Anexa/substitui link de unsubscribe: /api/v1/tracking/unsubscribe/{token}
       │
       ▼
PROVEDOR (Envio ao Destinatário)
       │
       ├─────────────────────────┬─────────────────────────┐
       ▼                         ▼                         ▼
Tracking Pixel             Click Redirect             Webhook Provedor
(GET /open/{token})       (GET /click/{token})       (POST /providers/webhooks/{driver})
       │                         │                         │
       └─────────────────────────┼─────────────────────────┘
                                 │
                                 ▼
                     ProviderEventNormalizer
                                 │
                                 ▼
                     MessageEventService::recordEvent()
                       - Deduplicação (provider_event_id / SHA256)
                       - Verificação de Precedência de Status
                       - Revogação de Consentimento LGPD (se UNSUBSCRIBE)
                       - Atualização de timestamps em messages
                       - Despacho de Job assíncrono
                                 │
                                 ▼
                     ProcessMessageEventJob (Queue: `analytics`)
                                 │
                                 ▼
                     CampaignAnalyticsService::recordEvent()
                       - Incrementa contadores atômicos
                       - Recalcula taxas de conversão e entrega
                       - Persiste snapshot em campaign_metrics
```

---

### 3. Lista de Tabelas Criadas ou Modificadas

1. **`message_links`** (Nova Tabela):
   - `id`: Chave primária.
   - `message_id`: FK referenciando `messages.id` com deleção em cascata.
   - `original_url`: URL original de destino (texto).
   - `link_token`: Token alfanumérico único indexado de 64 caracteres.
   - `clicks_count`: Contador inteiro de cliques.
   - `first_clicked_at`: Carimbo de data/hora do primeiro clique.
   - `last_clicked_at`: Carimbo de data/hora do último clique.
   - `created_at` / `updated_at`.

2. **`campaign_metrics`** (Nova Tabela Agregada):
   - `id`: Chave primária.
   - `campaign_id`: FK única referenciando `campaigns.id` com deleção em cascata.
   - `platform_id`: FK referenciando `platforms.id` com isolamento multi-tenant indexado.
   - Contadores quantitativos: `total_recipients`, `total_messages`, `sent_count`, `delivered_count`, `failed_count`, `bounced_count`, `rejected_count`, `opened_count`, `unique_opened_count`, `clicked_count`, `unique_clicked_count`, `unsubscribed_count`.
   - Taxas percentuais com precisão decimal: `delivery_rate`, `open_rate`, `click_rate`, `click_to_open_rate`, `bounce_rate`, `unsubscribe_rate`.
   - `last_calculated_at`, `created_at`, `updated_at`.

3. **`messages`** (Colunas Adicionadas):
   - `open_tracking_token`: Token único de 64 caracteres para o pixel de abertura.
   - `unsubscribe_token`: Token único de 64 caracteres para revogação de consentimento.
   - `player_id`: FK indexada referenciando `players.id` para enriquecimento analítico direto.
   - `opened_at`: Carimbo de data/hora da primeira abertura.
   - `clicked_at`: Carimbo de data/hora do primeiro clique.

4. **`message_events`** (Coluna Adicionada):
   - `provider_id`: FK anulável referenciando `providers.id` para segmentação analítica por gateway de envio.

---

### 4. Regras de Idempotência e Precedência de Status

#### 4.1. Idempotência
- Cada webhook recebido é analisado pelo `ProviderEventNormalizer`.
- Se o evento trouxer um identificador nativo (`provider_event_id`), verifica-se se já existe registro em `message_events` para o provedor ou mensagem.
- Na ausência de ID nativo, um hash SHA-256 determinístico é gerado concatenando `driver + message_id + event_type + timestamp + recipient`.
- Eventos já processados retornam imediatamente HTTP 200 informativo (`"Evento já processado anteriormente (idempotente)."`), sem persistir duplicatas ou disparar jobs de analytics.

#### 4.2. Precedência de Status
O ciclo de vida de uma mensagem segue uma hierarquia ordinal estrita para impedir que eventos defasados regridam o status atual:
- `QUEUED`: Nível 10
- `SENDING`: Nível 20
- `SENT`: Nível 30
- `DELIVERED`: Nível 40
- `OPENED`: Nível 50
- `CLICKED`: Nível 60
- `FAILED` / `BOUNCED` / `REJECTED`: Nível 70 (Estados terminais de falha)

**Regra de ouro**: O status da mensagem em `messages.status` só é atualizado se a precedência do novo evento for **estritamente maior** que a precedência do status atual. Por exemplo, um webhook `DELIVERED` tardio jamais rebaixará uma mensagem já marcada como `OPENED` ou `CLICKED`.

---

### 5. Endpoints de Tracking e Analytics Criados

| Método | Rota | Autenticação / Permissão | Descrição |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/tracking/open/{token}` | Pública (Rate Limit: 120/min) | Registra abertura única/múltipla e entrega GIF 1x1 transparente (no-cache) |
| `GET` | `/api/v1/tracking/click/{token}` | Pública (Rate Limit: 120/min) | Registra clique, atualiza métricas e redireciona (302) para URL original |
| `GET` | `/api/v1/tracking/unsubscribe/{token}` | Pública (Rate Limit: 120/min) | Revoga consentimento de marketing do jogador (LGPD) e exibe página HTML |
| `POST` | `/api/v1/providers/webhooks/{driver}` | Pública / Validação Secret | Ingestão e normalização de eventos de provedores externos |
| `GET` | `/api/v1/campaigns/{id}/analytics` | Sanctum (`analytics.view`) | Métricas consolidadas, taxas, funil de conversão e divisão por provedor |
| `GET` | `/api/v1/campaigns/{id}/events` | Sanctum (`analytics.view`) | Trilha cronológica paginada de eventos da campanha |
| `POST` | `/api/v1/campaigns/{id}/analytics/rebuild` | Sanctum (`campaigns.manage`) | Força reconstrução atômica das métricas agregadas da campanha |
| `GET` | `/api/v1/analytics/overview` | Sanctum (`analytics.view`) | KPIs globais da plataforma e volumes consolidados por período |
| `GET` | `/api/v1/analytics/campaigns` | Sanctum (`analytics.view`) | Lista comparativa de performance entre campanhas com ordenação e filtros |
| `GET` | `/api/v1/analytics/campaigns/export` | Sanctum (`analytics.export`) | Exportação instantânea de relatório de métricas em formato CSV |

---

### 6. Lista de Testes Automatizados e Asserções Executadas

A nova suíte de testes de Tracking e Analytics foi implementada em `tests/Feature/TrackingAndAnalyticsTest.php`, contendo **11 cenários de teste exaustivos e 57 asserções**:

1. `test_open_tracking_pixel_records_opened_event_and_updates_message`: Valida injeção de evento `OPENED`, atualização de `opened_at`, cabeçalhos no-cache e retorno de binário GIF 1x1.
2. `test_link_click_tracking_records_clicked_event_and_redirects`: Valida incremento em `message_links`, registro de evento `CLICKED` e redirecionamento HTTP 302.
3. `test_unsubscribe_revokes_player_marketing_consent`: Valida revogação atômica do consentimento de marketing do jogador e renderização de tela de confirmação LGPD.
4. `test_provider_webhook_normalizes_and_processes_events`: Testa normalização de webhooks do Brevo (`delivered`, `click`) e disparo assíncrono do job de métricas.
5. `test_provider_webhook_is_idempotent`: Garante que reenvios do mesmo webhook não geram eventos duplicados nem alteram contadores.
6. `test_status_precedence_prevents_status_downgrade`: Comprova que evento `DELIVERED` não rebaixa mensagem já em status `OPENED` ou `CLICKED`.
7. `test_campaign_analytics_api_returns_metrics_and_funnel`: Valida endpoint de métricas com funil de conversão completo e divisões por canal.
8. `test_campaign_events_endpoint_returns_event_stream`: Valida consulta paginada da trilha de auditoria e linha do tempo de eventos.
9. `test_rebuild_analytics_command_recalculates_metrics`: Valida comando Artisan `campaigns:rebuild-analytics` reconstruindo a tabela `campaign_metrics` a partir dos eventos gravados.
10. `test_analytics_overview_and_campaigns_list`: Valida endpoints executivos de overview global e listagem com ordenação de taxas.
11. `test_analytics_csv_export`: Valida geração e download de relatório de métricas em arquivo CSV.

---

### 7. Comprovação de 100% dos Testes Passando

- **Total de Testes da Suíte**: **153 testes**
- **Total de Asserções**: **636 asserções**
- **Taxa de Sucesso**: **100% GREEN**
- **Falhas / Erros / Warnings**: **0**

---

### 8. Status do Frontend Next.js (TypeScript, Build e Novas Telas)

O frontend Next.js 15 foi compilado com o comando de produção `npm run build`:
- **Resultado do Build**: Concluído com sucesso (0 erros de compilação, 0 erros de tipagem TypeScript).
- **Rotas Totais**: 20 rotas estáticas e dinâmicas perfeitamente otimizadas.
- **Novas Interfaces e Telas Entregues**:
  1. `/analytics`: Painel analítico executivo da plataforma com seletores de período temporal (Últimas 24h, 7 dias, 30 dias, Todo o período), cards de KPI agregados (Total de Envios, Taxa de Entrega %, Taxa de Abertura %, Taxa de Cliques %, Taxa de Rejeição/Bounce %), tabela comparativa de desempenho de campanhas com filtros e botão de exportação CSV.
  2. `/campaigns/[id]` (Abas Analíticas):
     - **Aba "Analytics & Funil"**: Funil de conversão visual interativo (Destinatários → Enviados → Entregues → Abertos → Clicados), cards analíticos com CTOR (Click-to-Open Rate) e Unsubscribe Rate, tabela de distribuição por Provedor de Mensageria e botão para forçar reconstrução das métricas.
     - **Aba "Eventos"**: Trilha cronológica de auditoria de eventos em tempo real com filtros por tipo (`DELIVERED`, `OPENED`, `CLICKED`, `BOUNCED`), badge de status colorido e metadados de execução.
  3. `/messages/[id]`: Visualizador do ciclo de vida individual da mensagem com **Stepper Linear Interativo** (`QUEUED` → `SENDING` → `SENT` → `DELIVERED` → `OPENED` → `CLICKED`), detalhes de canal, payload, links rastreados com contagem individual de cliques e log técnico detalhado.
  4. Menu de Navegação Global (`AppLayout`): Adição de link direto para `Analytics` com ícone de gráfico de barras e atualização do badge de versão para `FASE 9 • TRACKING & ANALYTICS`.

---

### 9. Métricas e Taxas Calculadas pelo Sistema

Todas as taxas analíticas são calculadas com proteção explícita contra divisão por zero:

1. **Taxa de Entrega (`delivery_rate`)**:
   $$\text{Taxa de Entrega} = \frac{\text{Mensagens Entregues}}{\text{Mensagens Enviadas}} \times 100$$
2. **Taxa de Abertura (`open_rate`)**:
   $$\text{Taxa de Abertura} = \frac{\text{Aberturas Únicas}}{\text{Mensagens Entregues}} \times 100$$
3. **Taxa de Cliques (`click_rate`)**:
   $$\text{Taxa de Cliques} = \frac{\text{Cliques Únicos}}{\text{Mensagens Entregues}} \times 100$$
4. **Click-to-Open Rate (`click_to_open_rate` / CTOR)**:
   $$\text{CTOR} = \frac{\text{Cliques Únicos}}{\text{Aberturas Únicas}} \times 100$$
5. **Taxa de Bounce / Rejeição (`bounce_rate`)**:
   $$\text{Taxa de Bounce} = \frac{\text{Mensagens Bounced + Falhadas}}{\text{Mensagens Enviadas}} \times 100$$
6. **Taxa de Cancelamento de Inscrição (`unsubscribe_rate`)**:
   $$\text{Taxa de Unsubscribe} = \frac{\text{Cancelamentos LGPD}}{\text{Mensagens Entregues}} \times 100$$

---

### 10. Proteções de Segurança, Rate Limit e LGPD Aplicadas

1. **Proteção contra Open Redirect**:
   - O redirecionamento de cliques valida estritamente a URL registrada na base de dados (`filter_var($url, FILTER_VALIDATE_URL)` com schemes autorizados `http` e `https`). Links fraudulentos ou com protocolos inseguros (`javascript:`, `data:`) são bloqueados com resposta 404.
2. **Mitigação de Abuso e Rate Limiting**:
   - Os endpoints públicos de tracking (`/open`, `/click`, `/unsubscribe`) são protegidos por middleware de limitação de taxa (`throttle:120,1` — 120 requisições por minuto por IP).
3. **Privacidade e Governança LGPD**:
   - Tokens de rastreamento e unsubscribe são strings criptograficamente seguras (`bin2hex(random_bytes(32))`), sem expor CPF, telefone, e-mail ou IDs internos de jogadores na URL.
   - O clique no link de unsubscribe revoga o consentimento de marketing imediatamente em nível de banco de dados (`marketing_consent = false`) e registra a ação no log de auditoria do jogador.
   - Endereços de IP de acesso são truncados e contatos são mascarados no frontend analítico.

---

### 11. Estrutura do Job Assíncrono e Comando de Rebuild

1. **`ProcessMessageEventJob`**:
   - Fila dedicada: `analytics`.
   - Processamento resiliente com 3 tentativas e backoff exponencial (`$backoff = [5, 15, 60]`).
   - Recebe o ID do `MessageEvent` e despacha para `CampaignAnalyticsService::recordEvent()`.
2. **`RebuildCampaignAnalyticsCommand`** (`campaigns:rebuild-analytics`):
   - Execução via CLI: `php artisan campaigns:rebuild-analytics {--campaign=} {--platform=}`.
   - Recalcula todos os totais e taxas a partir do histórico imutável de eventos (`message_events`) e destinatários (`campaign_recipients`).
   - Executável sob demanda também via chamada de API (`POST /api/v1/campaigns/{id}/analytics/rebuild`).

---

### 12. Permissões de RBAC Adicionadas

Foram integradas 4 novas permissões no `RoleAndPermissionSeeder`:
- `analytics.view`: Acesso à visualização de dashboards, funis, métricas e eventos.
- `analytics.export`: Permissão para download de dados analíticos e relatórios em formato CSV.
- `tracking.view`: Consulta a links rastreados e tokens de mensagens.
- `tracking.manage`: Permissão administrativa de gestão e reconstrução de métricas.

**Mapeamento por Perfil (Role)**:
- `SUPER_ADMIN` e `ADMIN`: Acesso total a todas as permissões analíticas.
- `MARKETING`: Acesso a `analytics.view`, `analytics.export` e `tracking.view`.
- `ANALYST`: Acesso de leitura e exportação a `analytics.view` e `analytics.export`.
- `SUPPORT`: Acesso operacional a `tracking.view` para consulta na timeline do jogador.

---

### 13. Garantia de Isolamento Multi-Plataforma

- A tabela agregada `campaign_metrics` implementa o trait `BelongsToPlatform` e é filtrada pelo escopo global `PlatformScope`.
- Endpoints analíticos validam o contexto do operador logado (`TenantPlatformContext`) através de cabeçalho `X-Platform-Id` ou atributos de sessão.
- Nenhum usuário de uma plataforma tem visibilidade sobre métricas, taxas, campanhas ou eventos de outra plataforma.

---

### 14. Limitações Respeitadas (Escopo Não Implementado)

Em estrito cumprimento às instruções do projeto:
- NÃO foram implementadas automações ou jornadas complexas baseadas em gatilhos de eventos.
- NÃO foram implementados testes A/B ou split de templates.
- NÃO foram implementadas campanhas recorrentes automáticas.
- NÃO foram integradas inteligências artificiais para geração ou otimização de conteúdo.
- NÃO foram implementados algoritmos de scoring preditivo de churn ou probabilidade de depósito.

---

### 15. Arquivos Criados e Modificados

#### Backend:
- `backend/database/migrations/2026_10_01_000008_create_tracking_and_analytics_tables.php` (Criado)
- `backend/app/Enums/MessageEventType.php` (Criado)
- `backend/app/Models/MessageLink.php` (Criado)
- `backend/app/Models/CampaignMetric.php` (Criado)
- `backend/app/Models/Message.php` (Modificado)
- `backend/app/Models/MessageEvent.php` (Modificado)
- `backend/app/Models/Campaign.php` (Modificado)
- `backend/app/Models/Platform.php` (Modificado)
- `backend/app/Services/Tracking/TrackingProcessor.php` (Criado)
- `backend/app/Services/Tracking/ProviderEventNormalizer.php` (Criado)
- `backend/app/Services/Tracking/MessageEventService.php` (Criado)
- `backend/app/Services/Analytics/CampaignAnalyticsService.php` (Criado)
- `backend/app/Services/Messaging/MessageService.php` (Modificado)
- `backend/app/Jobs/ProcessMessageEventJob.php` (Criado)
- `backend/app/Jobs/CreateCampaignMessagesJob.php` (Modificado)
- `backend/app/Console/Commands/RebuildCampaignAnalyticsCommand.php` (Criado)
- `backend/app/Http/Controllers/Api/V1/TrackingController.php` (Criado)
- `backend/app/Http/Controllers/Api/V1/ProviderWebhookController.php` (Modificado)
- `backend/app/Http/Controllers/Api/V1/AnalyticsController.php` (Criado)
- `backend/app/Policies/CampaignPolicy.php` (Modificado)
- `backend/database/seeders/RoleAndPermissionSeeder.php` (Modificado)
- `backend/routes/api.php` (Modificado)
- `backend/tests/Feature/TrackingAndAnalyticsTest.php` (Criado)

#### Frontend:
- `frontend/services/analytics-service.ts` (Criado)
- `frontend/app/campaigns/[id]/page.tsx` (Modificado — adicionadas abas de Analytics & Funil e Trilha de Eventos)
- `frontend/app/analytics/page.tsx` (Criado — dashboard analítico completo)
- `frontend/app/messages/[id]/page.tsx` (Modificado — timeline stepper visual e links rastreados)
- `frontend/components/layout/app-layout.tsx` (Modificado — link no menu e badge atualizado)

#### Documentação:
- `docs/TRACKING_ANALYTICS.md` (Criado)
- `docs/FASE_9_REPORT.md` (Criado)
- `README.md` (Modificado)

---

### 16. Conclusão

FASE 9 concluída. Aguardando aprovação para iniciar a próxima fase.
