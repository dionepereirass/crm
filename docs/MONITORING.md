# BET CRM — Arquitetura de Monitoramento, Métricas e Observabilidade

Este documento detalha os pontos de observabilidade, alertas, métricas de infraestrutura e endpoints de monitoramento de saúde do **BET CRM**.

---

## 1. Endpoints Nativos de Healthcheck

A aplicação expõe endpoints dedicados para balanceadores de carga, Kubernetes, AWS Route53 ou Uptime Kuma:

| Endpoint | Verificação Realizada | Resposta Esperada |
| :--- | :--- | :--- |
| `GET /api/v1/health` | Status geral da aplicação e versão ativa | `{"status": "ok", "app": "BET CRM"}` (HTTP 200) |
| `GET /api/v1/health/database` | Conexão com PostgreSQL e latência | `{"status": "ok", "connection": "pgsql"}` (HTTP 200) |
| `GET /api/v1/health/redis` | Conexão com Redis e ping | `{"status": "ok", "redis": "connected"}` (HTTP 200) |
| `GET /api/v1/health/queue` | Conectividade com a fila de mensageria | `{"status": "ok", "queue": "active"}` (HTTP 200) |
| `GET /api/v1/health/providers`| Status operacional dos provedores de envio | `{"status": "ok", "providers": [...]}` (HTTP 200) |

> [!NOTE]
> Os endpoints de healthcheck **nunca** expõem senhas, tokens ou dados sensíveis de conexão, apenas o status booleano e tempos de latência.

---

## 2. Monitoramento de Filas com Laravel Horizon

O dashboard do Horizon está disponível em `https://crm.seudominio.com/horizon` (protegido por autenticação e RBAC):

- **Taxa de Throughput**: Jobs processados por minuto em cada fila.
- **Latência de Espera**: Tempo que um job aguarda até ser assumido por um worker.
- **Filas Monitoradas**:
  - `webhooks`: Ingestão assíncrona de depósitos, saques e apostas.
  - `events`: Normalização de eventos para a timeline dos jogadores.
  - `messages`: Despacho de mensagens individuais e transacionais.
  - `emails` / `sms`: Fila de comunicação externa com provedores.
  - `campaigns` / `campaign_messages`: Despachos de marketing em massa.
  - `automations`: Execução dos nós da DAG de jornadas automáticas.
  - `reports` / `analytics`: Agregações analíticas e exportações CSV em background.

---

## 3. Métricas de Servidor e Recursos

Recomenda-se o uso de Prometheus + Grafana ou Datadog para coleta dos seguintes indicadores:

| Métrica | Limiar de Warning | Limiar de Alerta Crítico | Ação Recomendada |
| :--- | :--- | :--- | :--- |
| **Uso de CPU** | > 70% por 5 min | > 90% por 3 min | Aumentar workers ou escalar verticalmente o host |
| **Uso de Memória RAM** | > 75% | > 85% | Inspecionar vazamentos no Horizon e ajustar limites |
| **Espaço em Disco** | > 80% utilizado | > 90% utilizado | Executar expurgo de backups antigos e truncar logs |
| **Conexões PostgreSQL**| > 70% do max | > 85% do max | Ajustar connection pool ou pgbouncer |
| **Latência de Redis** | > 10ms | > 50ms | Verificar comandos pesados (KEYS vs SCAN) |

---

## 4. Gestão de Logs e Rotação

- **Logs do Laravel**: Armazenados em formato rotativo diário (`daily`), com retenção de **14 dias** em `storage/logs/laravel-YYYY-MM-DD.log`.
- **Logs do Nginx**:
  - Access log: `/var/log/nginx/access.log`
  - Error log: `/var/log/nginx/error.log`
- **Higienização Ativa**: O `SensitiveDataSanitizer` assegura que senhas, tokens de API e dados pessoais de jogadores (PII) sejam mascarados antes de serem gravados nos logs.
