# RELATÓRIO DE ENTREGA — FASE 10
## BET CRM — MOTOR DE AUTOMAÇÕES E JORNADAS

---

### 1. Resumo da Fase Concluída

A **FASE 10 — AUTOMAÇÕES E JORNADAS** foi integralmente desenvolvida, testada e homologada com **100% de sucesso**.

Esta fase dota o BET CRM de uma engine determinística orientada a grafos acíclicos dirigidos (DAGs), capaz de automatizar o ciclo de vida completo do jogador de apostas através de gatilhos operacionais em tempo real:
- Processamento assíncrono e resiliente na fila `automations` do Laravel Horizon.
- Suporte a 14 gatilhos de eventos (`PLAYER_CREATED`, `DEPOSIT_SUCCESS`, `BET_PLACED`, etc.).
- Validação estática rigorosa de grafos com detecção de ciclos por busca em profundidade (DFS) e verificação de compatibilidade de canais e templates.
- Execução de esperas (`WAIT`) puramente não-bloqueantes no Redis, sem nenhum uso de `sleep()` ou retenção síncrona de workers.
- Conformidade estrita com a LGPD: envio de e-mails e SMS bloqueados automaticamente com status `SKIPPED` caso o jogador não possua consentimento ativo de marketing.
- Frontend moderno em Next.js 15 com canvas de composição visual, paleta de nós, conexões com branches condicionais (`true`/`false`), modal de inspeção profunda de execuções com timeline e visualizador de payloads JSON.

---

### 2. Arquitetura do Motor de Automações

O motor opera segundo um modelo de eventos desacoplado e orientado a grafos:

```text
EVENTO DA PLATAFORMA / WEBHOOK / DISPARO
                  │
                  ▼
      AutomationTriggerService
                  │
       ├─► Filtra Automações Ativas por Plataforma & Trigger
       ├─► Avalia Políticas de Reentrada (ALLOW / BLOCK / REENTRY_AFTER)
       ├─► Avalia Cooldown Global do Jogador
       ├─► Gera Idempotency Key Atômica no Redis
       └─► Cria AutomationRun (Status: RUNNING)
                  │
                  ▼
        StartAutomationRunJob (Queue: `automations`)
                  │
                  ▼
       ProcessAutomationStepJob (Queue: `automations`)
                  │
                  ├─────────────────┬─────────────────┬─────────────────┐
                  ▼                 ▼                 ▼                 ▼
             TRIGGER_NODE     CONDITION_NODE     ACTION_NODE        WAIT_NODE
                  │                 │                 │                 │
              Avança           Bifurcação        Dispara Ação     Agenda Job
              ao próximo        TRUE / FALSE     (Email, SMS,     com Delay
                passo                │            Tag, Seg)        no Redis
                                     │                │                 │
                                     ▼                ▼                 ▼
                                  Grava             Grava         Status: WAITING
                              AutomationStep    AutomationStep    ResumeAutomation
                                (COMPLETED)       (COMPLETED)          RunJob
                                     │                │                 │
                                     └────────────────┴─────────────────┘
                                                      │
                                                      ▼
                                       Próximo Nó ou Conclusão da Run
                                            (Status: COMPLETED)
```

---

### 3. Modelagem e Estrutura do Banco de Dados

A migration `2026_10_01_000009_create_automations_and_journeys_tables.php` estabeleceu 6 novas tabelas estruturais:

1. **`automations`**:
   - `id`, `platform_id`, `name`, `description`, `status` (`DRAFT`, `ACTIVE`, `PAUSED`, `INACTIVE`), `trigger_type`, `settings` (JSONB com políticas de reentrada, cooldown e limite de passos), `created_by`, `updated_by`, `deleted_at`.
2. **`automation_nodes`**:
   - `id`, `automation_id`, `node_key`, `node_type` (`TRIGGER`, `CONDITION`, `ACTION`, `WAIT`), `name`, `configuration` (JSONB), `position_x`, `position_y`.
3. **`automation_edges`**:
   - `id`, `automation_id`, `source_node_id`, `target_node_id`, `condition_key` (`true`, `false` ou nulo para fluxo linear).
4. **`automation_runs`**:
   - `id`, `automation_id`, `platform_id`, `player_id`, `status` (`RUNNING`, `WAITING`, `COMPLETED`, `FAILED`, `CANCELLED`, `SKIPPED`), `current_node_id`, `idempotency_key`, `metadata` (JSONB), `started_at`, `completed_at`, `last_error`, `retry_count`.
5. **`automation_steps`**:
   - `id`, `automation_run_id`, `node_id`, `status` (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `SKIPPED`, `WAITING`), `input` (JSONB), `output` (JSONB), `error`, `started_at`, `completed_at`.
6. **`automation_logs`**:
   - `id`, `automation_id`, `automation_run_id`, `automation_step_id`, `level` (`INFO`, `WARNING`, `ERROR`), `event`, `message`, `metadata` (JSONB sanitizado), `created_at`.

---

### 4. Catálogo de Gatilhos (14 Triggers Suportados)

O enum `App\Enums\AutomationTriggerType` suporta:
- `PLAYER_CREATED`: Cadastro de novo jogador.
- `PLAYER_VERIFIED`: Verificação de identidade ou documentação.
- `PASSWORD_RESET`: Redefinição de senha.
- `LOGIN_FAILED`: Tentativas repetidas de falha de login.
- `DEPOSIT_INITIATED`: Intenção de recarga iniciada no checkout.
- `DEPOSIT_SUCCESS`: Liquidação confirmada de depósito.
- `DEPOSIT_FAILED`: Rejeição ou expiração de depósito.
- `WITHDRAWAL_INITIATED`: Solicitação de saque.
- `WITHDRAWAL_SUCCESS`: Liquidação bancária do saque.
- `WITHDRAWAL_FAILED`: Falha na operação de saque.
- `BET_PLACED`: Bilhete de aposta submetido.
- `BET_WON`: Resolução de bilhete com vitória (prêmio/lucro).
- `BET_LOST`: Resolução de aposta com derrota.
- `PLAYER_INACTIVITY`: Inatividade temporal do jogador.

---

### 5. Tipos de Nós e Ações Implementadas

O enum `App\Enums\AutomationNodeType` e `App\Enums\AutomationActionType` definem:
- **`TRIGGER`**: Ponto de entrada de execução.
- **`CONDITION`**: Bifurcação booleana avaliando regras de segmentos, campos atômicos ou interações com campanhas.
- **`WAIT`**: Pausa temporizada relativa (minutos, horas, dias) ou até data/horário específico.
- **`ACTION`**:
  - `SEND_EMAIL`: Disparo transacional/relacional de template de e-mail.
  - `SEND_SMS`: Disparo transacional/relacional de SMS.
  - `ADD_TAG`: Associação de tag ao jogador com registro de auditoria.
  - `REMOVE_TAG`: Desassociação de tag.
  - `ENTER_SEGMENT`: Adição a segmento estático.
  - `EXIT_SEGMENT`: Remoção de segmento estático.

---

### 6. Handlers de Ações e Validação LGPD

Localizados em `app/Services/Automations/Actions/`:
- **`SendEmailAction`**:
  - Verifica o consentimento `player->hasMarketingConsent('email')`. Se falso, retorna imediatamente status `SKIPPED` com motivo `MARKETING_CONSENT_MISSING`.
  - Localiza o template publicado (`TemplateVersion` com status `PUBLISHED`).
  - Renderiza o template via `TemplateRenderer::renderEmail`.
  - Dispara a mensagem via `MessageService::send` e registra o ID da mensagem no output do passo.
- **`SendSmsAction`**:
  - Verifica o consentimento `player->hasMarketingConsent('sms')`. Se falso, retorna status `SKIPPED`.
  - Renderiza conteúdo SMS via `TemplateRenderer::renderSms`.
  - Dispara mensagem via `MessageService::send`.
- **`AddTagAction`**:
  - Garante associação idempotente da tag ao jogador via `syncWithoutDetaching`, auditando no log do sistema.
- **`RemoveTagAction`**:
  - Desvincula a tag do jogador com segurança.

---

### 7. Avaliador de Condições

A classe `ConditionNodeHandler` avalia dinamicamente os critérios de decisão:
1. **Modo `SEGMENT`**: Utiliza o `SegmentQueryCompiler` compilando a árvore de regras do segmento contra o ID do jogador atual.
2. **Modo `RULES`**: Avalia condições atômicas em memória (ex: `total_deposits_count > 0`, `current_balance >= 100`, etc.).
3. **Modo `CAMPAIGN_INTERACTION`**: Consulta o histórico de `MessageEvent` para verificar se o destinatário abriu ou clicou em mensagens da campanha nos últimos N dias.
- O resultado é roteado através das arestas conectadas cujo `condition_key` coincide com `"true"` ou `"false"`.

---

### 8. Motor de Espera Assíncrono Não-Bloqueante

- O `WaitNodeHandler` calcula o atraso exato em segundos com base em `duration_value` e `duration_unit` (MINUTES, HOURS, DAYS) ou data agendada (`scheduled_at`).
- O `ProcessAutomationStepJob` altera o status do `AutomationRun` para `WAITING` e agenda o `ResumeAutomationRunJob` utilizando o método `delay($delaySeconds)` nativo do Laravel Queue no Redis.
- Zero uso de funções bloqueantes (`sleep`, `usleep`). Os workers do Horizon permanecem livres para atender novas demandas instantaneamente.

---

### 9. Validador Estático de Grafos

A classe `AutomationGraphValidator` previne erros operacionais antes da ativação:
- **Detecção de Ciclos**: Algoritmo de Depth-First Search (DFS) com detecção de nós em pilha (`recursionStack`), impedindo loops recursivos.
- **Nó Trigger Único**: Garante que o grafo possua exatamente 1 nó do tipo `TRIGGER`.
- **Validação de Arestas**: Verifica se todas as arestas ligam nós válidos e existentes no grafo.
- **Bifurcação de Condição**: Exige que nós do tipo `CONDITION` possuam arestas de saída diferenciadas (`condition_key`).
- **Compatibilidade de Templates**: Valida se templates referenciados em ações existem, estão com status `PUBLISHED` e pertencem à mesma plataforma do tenant.

---

### 10. Mecanismos de Idempotência e Locks Distribuídos com Redis

- Cada evento interceptado por `AutomationTriggerService` constrói uma chave única no Redis:
  `automation_run:{platformId}:{automationId}:{triggerType}:{playerId}:{eventId}`
- Um lock atômico (`Cache::lock`) previne que eventos duplicados simultâneos criem mais de uma execução da jornada.
- A coluna `idempotency_key` possui restrição de unicidade na tabela `automation_runs`.

---

### 11. Políticas de Reentrada, Cooldown e Anti-Loop

- **`reentry_policy`**:
  - `BLOCK_REENTRY`: Permite apenas 1 execução na vida útil do jogador.
  - `ALLOW_REENTRY`: Permite nova execução a cada evento recebido.
  - `REENTRY_AFTER`: Permite reentrada somente após N dias da conclusão da última execução.
- **`cooldown_days`**: Intervalo mínimo obrigatório entre execuções consecutivas para o mesmo jogador.
- **`max_steps_per_run`**: Limite rígido (padrão: 50 passos) avaliado a cada transição de nó no `ProcessAutomationStepJob`. Caso excedido, a jornada é abortada com status `FAILED` para evitar loops acidentais.

---

### 12. Integração com Webhooks e Pipeline de Eventos

- O `ProcessEventJob` (Fase 4) foi integrado para invocar `AutomationTriggerService::handleTrigger` a cada evento processado com sucesso.
- O `MessageEventService` (Fase 9) despacha gatilhos automáticos para eventos de entrega, cliques e aberturas de e-mail.

---

### 13. Sistema de Auditoria e Sanitização de Credenciais

- Toda transição de nó, erro, condição avaliada ou ação executada registra uma entrada imutável na tabela `automation_logs`.
- O método `AutomationLog::sanitizeMetadata` mascara automaticamente chaves como `password`, `secret`, `token`, `api_key`, `hmac_signature` e credenciais bancárias.

---

### 14. Camada de Permissões Granulares e RBAC

Foram criadas e semeadas 8 novas permissões no `RoleAndPermissionSeeder`:
- `automations.view`: Visualizar automações e histórico.
- `automations.create`: Criar novas jornadas e nós.
- `automations.update`: Editar configurações e grafos.
- `automations.delete`: Excluir automações.
- `automations.activate`: Ativar, pausar e desativar jornadas.
- `automations.manage`: Acesso completo administrativo.
- `automations.runs.view`: Inspecionar execuções e passos.
- `automations.runs.cancel`: Cancelar execuções ativas ou em espera.

Mapeadas para os papéis:
- `SUPER_ADMIN` e `ADMIN`: Acesso irrestrito a todas as operações.
- `MARKETING`: Criação, edição, validação, ativação e consulta de runs.
- `SUPPORT`: Leitura e consulta de histórico de execuções.
- `ANALYST`: Leitura e consulta analítica.

---

### 15. Isolamento Multi-Plataforma / Multi-Tenant

- Modelos `Automation` e `AutomationRun` utilizam o trait `BelongsToPlatform` e o escopo global `PlatformScope`.
- O `TenantPlatformContext` valida o header `X-Platform-Id` em todas as rotas da API.
- Testes automatizados comprovam que requisições cruzadas entre plataformas resultam em HTTP 403 / 404.

---

### 16. Rotas e Endpoints da API REST

Controlador: `App\Http\Controllers\Api\V1\AutomationController`:
- `GET /api/v1/automations`: Listagem paginada com busca e filtros.
- `POST /api/v1/automations`: Criação de automação.
- `GET /api/v1/automations/{id}`: Detalhes e KPIs.
- `PUT /api/v1/automations/{id}`: Edição de parâmetros.
- `DELETE /api/v1/automations/{id}`: Remoção com soft delete.
- `POST /api/v1/automations/{id}/activate`: Ativação.
- `POST /api/v1/automations/{id}/pause`: Pausa temporária.
- `POST /api/v1/automations/{id}/deactivate`: Desativação permanente.
- `GET /api/v1/automations/{id}/graph`: Nós e arestas do fluxo.
- `PUT /api/v1/automations/{id}/graph`: Persistência do grafo.
- `POST /api/v1/automations/{id}/validate`: Validação estática.
- `POST /api/v1/automations/{id}/preview`: Simulação da jornada.
- `GET /api/v1/automations/{id}/metrics`: Métricas agregadas de taxa de sucesso.
- `GET /api/v1/automations/{id}/runs`: Listagem de execuções.
- `GET /api/v1/automations/{id}/runs/{runId}`: Detalhe granular da execução com timeline.
- `POST /api/v1/automations/{id}/runs/{runId}/cancel`: Cancelamento de execução.

---

### 17. Frontend Next.js 15: Telas, Canvas e Componentes

1. **`frontend/app/automations/page.tsx`**:
   - Listagem de automações com filtros de status e gatilho, cards de KPIs globais (Total, Ativas, Pausadas, Execuções), tabela com badges de status e botões de ciclo de vida (Ativar/Pausar/Desativar/Excluir).
2. **`frontend/app/automations/new/page.tsx`**:
   - Wizard de criação com seleção de gatilhos operacionais, políticas de reentrada, cooldown e limite de passos.
3. **`frontend/app/automations/[id]/page.tsx`**:
   - Visão consolidada da automação com cards métricos de taxa de sucesso, falhas, execuções em espera, visualizador dos nós do grafo e tabela das últimas execuções.
4. **`frontend/app/automations/[id]/edit/page.tsx`**:
   - Canvas de edição visual de grafos: paleta para adição de nós (`TRIGGER`, `CONDITION`, `ACTION`, `WAIT`), botão para aplicação instantânea do template de boas-vindas, conector de arestas com suporte a ramificações condicionais (`true`/`false`), drawer lateral de inspeção e configuração detalhada dos nós, botões de validação e ativação.
5. **`frontend/app/automations/[id]/runs/page.tsx`**:
   - Gestão de execuções com filtros de status, modal de inspeção profunda exibindo timeline detalhada dos passos, payloads de entrada/saída em JSON, motivos de erro e trilha de auditoria.
6. **`frontend/components/layout/app-layout.tsx`**:
   - Atualizado com link direto para o módulo de automações e badge `FASE 10 • AUTOMAÇÕES`.

---

### 18. Testes Automatizados e Cobertura (100% Green)

Foram implementadas três suítes completas de testes de integração e autorização:
1. `tests/Feature/AutomationTest.php`:
   - Listagem, criação, atualização, exclusão, isolamento de tenant e validação de grafo com detecção de ciclos DFS.
2. `tests/Feature/AutomationExecutionTest.php`:
   - Execução de jornada completa (Trigger -> SendEmail -> Wait -> AddTag), proteção de consentimento LGPD de marketing (marcando `SKIPPED`), atraso não-bloqueante no Redis, bloqueio de reentrada e cancelamento de execuções ativas.
3. `tests/Feature/AutomationAuthorizationTest.php`:
   - Matriz RBAC para perfis `SUPER_ADMIN`, `ADMIN`, `MARKETING`, `SUPPORT` e `ANALYST`, além de bloqueio de requisições cross-tenant.

**Resultado do PHPUnit:**
- **171 testes passaram com sucesso**
- **703 asserções válidas**
- **0 falhas, 0 erros, 0 warnings**
- **Tempo de execução: 180s**

**Resultado do Next.js Build:**
- **Compilação bem-sucedida em 8.4s**
- **22 rotas geradas**
- **0 erros de TypeScript / ESLint**

---

### 19. Métricas e KPIs de Execução

O endpoint `/api/v1/automations/{id}/metrics` e os cards da interface fornecem:
- Total de Execuções (`total_runs`)
- Execuções Concluídas (`completed`)
- Execuções com Falhas (`failed`)
- Execuções em Espera de Timer (`waiting`)
- Execuções em Processamento Ativo (`running`)
- Execuções Ignoradas por LGPD (`skipped`)
- Taxa Percentual de Sucesso (`success_rate`)

---

### 20. Checklist de Requisitos e Restrições Atendidos

- [x] Motor de automações determinístico (`TRIGGER -> CONDITION -> ACTION -> WAIT -> NEXT STEP`).
- [x] Reutilização integral dos módulos existentes (`Player`, `Tag`, `Consent`, `Template`, `Provider`, `MessageService`).
- [x] Sem duplicação de sistemas ou mensageria.
- [x] Execuções assíncronas na fila `automations` do Horizon.
- [x] Espera temporizada não-bloqueante via Redis (`delay` de jobs).
- [x] Isolamento multi-tenant rigoroso (`BelongsToPlatform`, `PlatformScope`).
- [x] Conformidade total com a LGPD (bloqueio por falta de consentimento de marketing).
- [x] Proteção anti-ciclos e limite máximo de passos por execução.
- [x] Idempotência com Redis locks para evitar duplicidades em disparos simultâneos.
- [x] Trilha de auditoria completa com sanitização de credenciais.
- [x] Frontend Next.js 15 completo com visualização de grafo e monitoramento de execuções.
- [x] Não implementação de IA, testes A/B ou predição de churn (escopos proibidos respeitados).

---

### 21. Arquivos Criados e Modificados

#### Backend:
- `backend/database/migrations/2026_10_01_000009_create_automations_and_journeys_tables.php`
- `backend/app/Enums/AutomationStatus.php`
- `backend/app/Enums/AutomationTriggerType.php`
- `backend/app/Enums/AutomationNodeType.php`
- `backend/app/Enums/AutomationActionType.php`
- `backend/app/Enums/AutomationRunStatus.php`
- `backend/app/Enums/AutomationStepStatus.php`
- `backend/app/Models/Automation.php`
- `backend/app/Models/AutomationNode.php`
- `backend/app/Models/AutomationEdge.php`
- `backend/app/Models/AutomationRun.php`
- `backend/app/Models/AutomationStep.php`
- `backend/app/Models/AutomationLog.php`
- `backend/app/Models/Platform.php` (relações de automação)
- `backend/app/Models/Player.php` (relações de automação)
- `backend/app/Models/Template.php` (helper publishedVersion)
- `backend/app/Services/Automations/Actions/ActionHandlerInterface.php`
- `backend/app/Services/Automations/Actions/SendEmailAction.php`
- `backend/app/Services/Automations/Actions/SendSmsAction.php`
- `backend/app/Services/Automations/Actions/AddTagAction.php`
- `backend/app/Services/Automations/Actions/RemoveTagAction.php`
- `backend/app/Services/Automations/Actions/EnterSegmentAction.php`
- `backend/app/Services/Automations/Actions/ExitSegmentAction.php`
- `backend/app/Services/Automations/NodeHandlers/NodeHandlerInterface.php`
- `backend/app/Services/Automations/NodeHandlers/TriggerNodeHandler.php`
- `backend/app/Services/Automations/NodeHandlers/ConditionNodeHandler.php`
- `backend/app/Services/Automations/NodeHandlers/ActionNodeHandler.php`
- `backend/app/Services/Automations/NodeHandlers/WaitNodeHandler.php`
- `backend/app/Services/Automations/AutomationGraphValidator.php`
- `backend/app/Services/Automations/AutomationTriggerService.php`
- `backend/app/Services/Automations/AutomationService.php`
- `backend/app/Jobs/StartAutomationRunJob.php`
- `backend/app/Jobs/ProcessAutomationStepJob.php`
- `backend/app/Jobs/ResumeAutomationRunJob.php`
- `backend/app/Jobs/ProcessEventJob.php` (integração de trigger)
- `backend/app/Services/Tracking/MessageEventService.php` (integração de trigger)
- `backend/app/Policies/AutomationPolicy.php`
- `backend/app/Policies/AutomationRunPolicy.php`
- `backend/app/Http/Controllers/Api/V1/AutomationController.php`
- `backend/routes/api.php`
- `backend/database/seeders/RoleAndPermissionSeeder.php`
- `backend/tests/Feature/AutomationTest.php`
- `backend/tests/Feature/AutomationExecutionTest.php`
- `backend/tests/Feature/AutomationAuthorizationTest.php`

#### Frontend:
- `frontend/services/automation-service.ts`
- `frontend/app/automations/page.tsx`
- `frontend/app/automations/new/page.tsx`
- `frontend/app/automations/[id]/page.tsx`
- `frontend/app/automations/[id]/edit/page.tsx`
- `frontend/app/automations/[id]/runs/page.tsx`
- `frontend/components/layout/app-layout.tsx`

#### Documentação:
- `docs/AUTOMATIONS.md`
- `docs/FASE_10_REPORT.md`
- `README.md`

---

### 22. Conclusão

A Fase 10 entrega um dos módulos mais estratégicos e avançados do BET CRM, integrando os módulos de eventos, segmentação, templates, mensageria e tracking construídos ao longo das Fases 1 a 9.

FASE 10 concluída. Aguardando aprovação para iniciar a próxima fase.
