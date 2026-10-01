# BET CRM — MOTOR DE AUTOMAÇÕES E JORNADAS (FASE 10)

## 1. Visão Geral da Arquitetura

O **Motor de Automações e Jornadas do BET CRM** é uma engine determinística orientada a grafos direcionados acíclicos (DAGs) que orquestra interações automáticas em tempo real com os jogadores de apostas.

O ciclo operacional segue o fluxo:

```text
GATILHO (Trigger)
       ↓
  CONDIÇÃO (IF/ELSE)
  ↙              ↘
TRUE            FALSE
  ↓               ↓
AÇÃO (Email/SMS) AÇÃO (Tag/SMS)
  ↓               ↓
ESPERA (Wait)   ESPERA (Wait)
  ↓               ↓
PRÓXIMO PASSO...
```

---

## 2. Modelagem de Dados

### 2.1 Tabelas
- `automations`: Armazena a definição da automação, nome, status (`DRAFT`, `ACTIVE`, `PAUSED`, `INACTIVE`), `trigger_type`, configurações de reentrada, cooldown e limite máximo de passos por execução.
- `automation_nodes`: Vértices do grafo (`TRIGGER`, `CONDITION`, `ACTION`, `WAIT`), contendo JSONB `configuration`, chave única `node_key` e coordenadas visuais `position_x`, `position_y`.
- `automation_edges`: Arestas do grafo com `source_node_id`, `target_node_id` e rótulo de condição `condition_key` (`true`, `false`, etc.).
- `automation_runs`: Instâncias de execução individual vinculadas a um `player_id` e `platform_id`. Possui status (`RUNNING`, `WAITING`, `COMPLETED`, `FAILED`, `CANCELLED`, `SKIPPED`), `idempotency_key` único e ponteiro para o nó atual `current_node_id`.
- `automation_steps`: Registro granular de cada nó executado durante a jornada, contendo payloads `input`, `output`, mensagem de erro e timestamps `started_at` e `completed_at`.
- `automation_logs`: Trilha de auditoria imutável com níveis (`INFO`, `WARNING`, `ERROR`), evento, mensagem e metadados com sanitização rigorosa de credenciais e tokens.

---

## 3. Gatilhos Suportados (AutomationTriggerType)

1. `PLAYER_CREATED`: Novo cadastro na plataforma.
2. `PLAYER_VERIFIED`: Validação de identidade ou telefone/email.
3. `DEPOSIT_INITIATED`: Intenção de depósito (PIX gerado, boleto impresso).
4. `DEPOSIT_SUCCESS`: Primeiro ou novo depósito liquidado com sucesso.
5. `DEPOSIT_FAILED`: Falha na liquidação do depósito.
6. `WITHDRAWAL_INITIATED`: Solicitação de resgate de saldo.
7. `WITHDRAWAL_SUCCESS`: Liquidação do saque na conta bancária.
8. `WITHDRAWAL_FAILED`: Falha no processamento de saque.
9. `BET_PLACED`: Bilhete de aposta esportiva ou rodada de cassino efetuada.
10. `BET_WON`: Aposta resolvida com vitória.
11. `BET_LOST`: Aposta resolvida com derrota (churn/cashback).
12. `LOGIN_FAILED`: Tentativa inválida de login.
13. `PASSWORD_RESET`: Solicitação de recuperação de credenciais.
14. `PLAYER_INACTIVITY`: Ausência de movimentação por N dias.

---

## 4. Tipos de Nós e Ações

### 4.1 Nós
- **TRIGGER**: Nó raiz da jornada. Disparado pelo evento da plataforma.
- **CONDITION**: Nó de decisão que bifurca o fluxo em branches baseadas no resultado booleano (`true` ou `false`). Avalia filtros de segmentos (`SegmentQueryCompiler`), regras atômicas de perfil ou histórico de interações (`MessageEventService`).
- **ACTION**: Execução determinística de comandos de negócio.
- **WAIT**: Suspensão não-bloqueante do fluxo (`ProcessAutomationStepJob` agenda `ResumeAutomationRunJob` com `delay()`).

### 4.2 Ações Implementadas
- `SEND_EMAIL`: Renderiza o template de e-mail aprovado (`TemplateRenderer`) e dispara mensagem via `MessageService`. Valida obrigatoriamente consentimento LGPD de marketing (`hasMarketingConsent('email')`). Se ausente, a ação é marcada como `SKIPPED` com `MARKETING_CONSENT_MISSING`.
- `SEND_SMS`: Renderiza template SMS publicado e envia via provedor ativo de SMS. Valida obrigatoriamente consentimento LGPD de marketing (`hasMarketingConsent('sms')`).
- `ADD_TAG`: Anexa tag ao perfil do jogador de forma idempotente, registrando log na auditoria.
- `REMOVE_TAG`: Remove tag do perfil com segurança.
- `ENTER_SEGMENT` / `EXIT_SEGMENT`: Atualização de pertinência em segmentos.

---

## 5. Garantias de Segurança, Idempotência e Performance

1. **Locks Atômicos com Redis**:
   - Cada evento gera uma chave de idempotência `automation_run:{platform}:{automation}:{trigger}:{player}:{eventId}`.
   - O lock atômico garante que múltiplos webhooks simultâneos do mesmo evento nunca instanciem execuções duplicadas.
2. **Proteção Anti-Loop e Ciclos**:
   - `AutomationGraphValidator`: Executa busca em profundidade (DFS) para detectar e proibir ciclos estáticos durante a criação e validação do grafo.
   - `max_steps_per_run`: Limita a quantidade máxima de nós por execução (padrão: 50 passos). Se ultrapassado, a jornada é abortada com status `FAILED` e log de alerta.
3. **Execução Assíncrona Não-Bloqueante**:
   - A fila `automations` processa todos os passos de forma desacoplada no Laravel Horizon.
   - Nós do tipo `WAIT` nunca utilizam `sleep()` ou travam workers: o status do run transita para `WAITING` e o `ResumeAutomationRunJob` é agendado no Redis com delay exato em segundos.
4. **Isolamento Multi-Tenant**:
   - Todos os modelos implementam `BelongsToPlatform` e `PlatformScope`.
   - APIs protegidas pelo middleware `TenantPlatformContext` e `AutomationPolicy` / `AutomationRunPolicy`.
5. **Auditoria e Sanitização**:
   - `AutomationLog` mascara senhas, tokens de API, segredos HMAC e dados sensíveis antes da persistência.

---

## 6. Endpoints da API

- `GET /api/v1/automations`: Listagem de automações com filtros de status e gatilho.
- `POST /api/v1/automations`: Criação de nova automação.
- `GET /api/v1/automations/{id}`: Detalhes e KPIs de execução.
- `PUT /api/v1/automations/{id}`: Atualização de configurações.
- `DELETE /api/v1/automations/{id}`: Exclusão com soft delete.
- `POST /api/v1/automations/{id}/activate`: Ativação da jornada.
- `POST /api/v1/automations/{id}/pause`: Pausa temporária.
- `POST /api/v1/automations/{id}/deactivate`: Desativação definitiva.
- `GET /api/v1/automations/{id}/graph`: Recuperação dos nós e arestas.
- `PUT /api/v1/automations/{id}/graph`: Persistência do grafo.
- `POST /api/v1/automations/{id}/validate`: Validação estática do grafo (ausência de ciclos, nó trigger único, conectores válidos).
- `POST /api/v1/automations/{id}/preview`: Simulação estrutural do fluxo.
- `GET /api/v1/automations/{id}/metrics`: Métricas agregadas e taxa de conclusão.
- `GET /api/v1/automations/{id}/runs`: Listagem paginada de execuções com filtros.
- `GET /api/v1/automations/{id}/runs/{runId}`: Inspeção profunda dos passos, inputs, outputs e logs.
- `POST /api/v1/automations/{id}/runs/{runId}/cancel`: Cancelamento de execução em andamento ou em espera.
