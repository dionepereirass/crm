# BET CRM — MOTOR DE ALERTAS OPERACIONAIS E INCIDENTES

## 1. Visão Geral

O **Motor de Alertas Operacionais** do BET CRM monitora continuamente a integridade da plataforma, a saúde das integrações com provedores de mensageria, o comportamento da base de jogadores e os prazos legais de atendimento da LGPD.

```text
       AVALIAÇÃO DE MÉTRICAS OPERACIONAIS
 (Falhas de Provedor, Queda de Entrega, SLA DSR, Churn)
                      │
                      ▼
     MOTOR DE REGRAS (AlertRuleService)
  Operadores: GT (>) | GTE (>=) | LT (<) | LTE (<=) | EQ (=)
  Cooldown por Regra: Evita reemissão em loop (anti-spam)
                      │
                      ▼
        INCIDENTE DETECTADO (TRIGGERED)
  Severidades: INFO | WARNING | CRITICAL
                      │
         ┌────────────┴────────────┐
         ▼                         ▼
RECONHECIDO (ACKNOWLEDGED)    RESOLVIDO (RESOLVED)
Registra Operador & Timestamp Registra Notas de Resolução & Auditoria
```

---

## 2. Métricas Monitoradas e Suportadas

| Métrica | Descrição do Gatilho | Casos Típicos de Uso |
|---|---|---|
| `PROVIDER_FAILURES` | Contagem ou taxa de falhas em provedores de E-mail/SMS | Detectar instabilidades no Brevo, Zenvia ou Twilio |
| `DELIVERY_DROP` | Queda na taxa de entrega de mensagens | Identificar bloqueio de IP ou problemas com operadoras |
| `CHURN_INCREASE` | Aumento acelerado na taxa de churn de jogadores | Monitorar insatisfação ou migração para concorrência |
| `INACTIVE_PLAYERS` | Contagem de jogadores inativos acima do limite | Alerta para ativação de réguas de reengajamento |
| `MESSAGE_QUEUE_BACKLOG` | Acúmulo de mensagens pendentes na fila | Detectar lentidão no worker ou gargalo na infraestrutura |
| `AUTOMATION_FAILURES` | Taxa de falhas na execução de nós de automação | Alerta para erro em webhook ou condição mal configurada |
| `DSR_NEAR_SLA` | Solicitações de titulares próximas ao prazo limite LGPD | Prevenir violações regulatórias da ANPD (SLA &le; 48h) |
| `CIRCUIT_BREAKER_OPEN` | Circuito aberto em provedores de mensageria | Identificar provedores que entraram em fallback |
| `WEBHOOK_ERRORS` | Falhas na ingestão de webhooks transacionais | Alerta para falha de comunicação com a plataforma de apostas |

---

## 3. Estrutura das Regras de Alerta (`alert_rules`)

* `platform_id`: Isolamento multi-tenant obrigatório.
* `name`: Descrição legível da regra (ex: *"Taxa de Falha Twilio > 5%"*).
* `metric`: Identificador da métrica monitorada.
* `operator`: Operador de comparação (`GT`, `GTE`, `LT`, `LTE`, `EQ`).
* `threshold`: Limite numérico de disparo.
* `severity`: Nível do alerta (`INFO`, `WARNING`, `CRITICAL`).
* `cooldown_minutes`: Janela mínima em minutos entre disparos consecutivos da mesma regra (padrão: 60 minutos).
* `active`: Booleano para ativar/desativar a regra.

---

## 4. Ciclo de Vida dos Alertas (`operational_alerts`)

1. **`TRIGGERED` (Disparado):**
   * O alerta é gerado quando a métrica avaliada ultrapassa o limiar definido.
   * Apresentado imediatamente no banner do Dashboard e no feed operacional com indicador visual pulsante.
2. **`ACKNOWLEDGED` (Reconhecido):**
   * O operador responsável assume a tratativa do incidente.
   * O sistema registra `acknowledged_at` e `acknowledged_by` (ID do usuário operador).
3. **`RESOLVED` (Resolvido):**
   * O incidente foi mitigado ou concluído.
   * O operador preenche obrigatoriamente as **Notas de Resolução** para trilha de auditoria (`resolved_at`, `resolved_by`, `resolution_notes`).

---

## 5. Catálogo de Endpoints REST

| Método | Endpoint | Permissão RBAC | Descrição |
|---|---|---|---|
| `GET` | `/api/v1/alerts` | `alerts.view` | Lista alertas operacionais com filtros de status e severidade |
| `POST` | `/api/v1/alerts/{id}/acknowledge` | `alerts.resolve` | Reconhece o alerta pelo operador ativo |
| `POST` | `/api/v1/alerts/{id}/resolve` | `alerts.resolve` | Resolve o alerta com notas de conclusão |
| `GET` | `/api/v1/alerts/rules` | `alerts.view` | Lista as regras de monitoramento configuradas |
| `POST` | `/api/v1/alerts/rules` | `alerts.create` | Cria uma nova regra de alerta |
| `PUT` | `/api/v1/alerts/rules/{id}` | `alerts.update` | Atualiza parâmetros ou ativa/desativa uma regra |
| `DELETE`| `/api/v1/alerts/rules/{id}` | `alerts.delete` | Remove uma regra de alerta |
| `POST` | `/api/v1/alerts/evaluate` | `alerts.resolve` | Dispara a avaliação imediata de todas as regras |
