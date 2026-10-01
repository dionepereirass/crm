# BET CRM — MANUAL DE OPERAÇÕES E RUNBOOK

## 1. Visão Geral da Operação

Este documento estabelece as diretrizes de sustentação, operação diária, manutenção de infraestrutura, rotinas de monitoramento e procedimentos para tratamento de incidentes no ambiente de produção do **BET CRM**.

---

## 2. Arquitetura de Contêineres e Processos

```text
[ Internet / Operadores de Apostas ]
              │ (HTTPS :443 / :80)
              ▼
    ┌───────────────────┐
    │    Nginx (Web)    │
    └─────────┬─────────┘
              │
    ┌─────────┴─────────┐
    ▼                   ▼
┌──────────────┐   ┌────────────────────────┐
│ Next.js App  │   │      PHP-FPM (API)     │
│  (Port 3000) │   │     (Laravel Core)     │
└──────────────┘   └─────┬────────────┬─────┘
                         │            │
                         ▼            ▼
             ┌───────────────┐   ┌──────────────────────────┐
             │ PostgreSQL 16 │   │         Redis 7          │
             │ (Porta 5432)  │   │ (Cache, Sessão, Horizon) │
             └───────────────┘   └────────────┬─────────────┘
                                              │
                                 ┌────────────┴─────────────┐
                                 ▼                          ▼
                       ┌───────────────────┐     ┌──────────────────┐
                       │  Laravel Horizon  │     │ Laravel Schedule │
                       │ (Worker Fast/Bat) │     │ (Cron Jobs 1min) │
                       └───────────────────┘     └──────────────────┘
```

---

## 3. Gestão de Filas e Processamento Assíncrono (Horizon)

O Laravel Horizon é o orquestrador das filas assíncronas do BET CRM.

### Comandos Essenciais no Host de Produção
```bash
# Verificar status dos supervisores do Horizon
docker compose -f docker-compose.prod.yml exec php php artisan horizon:status

# Pausar recebimento de novos jobs (modo de drenagem para manutenção)
docker compose -f docker-compose.prod.yml exec php php artisan horizon:pause

# Retomar processamento de jobs
docker compose -f docker-compose.prod.yml exec php php artisan horizon:continue

# Terminar e reiniciar graciosamente todos os workers
docker compose -f docker-compose.prod.yml exec php php artisan horizon:terminate
```

### Gestão de Falhas em Jobs (`failed_jobs`)
Quando um evento externo ou provedor de mensageria falha além do limite de retentativas:
```bash
# Listar jobs com falha
docker compose -f docker-compose.prod.yml exec php php artisan queue:failed

# Reprocessar um job específico por ID
docker compose -f docker-compose.prod.yml exec php php artisan queue:retry <JOB_ID>

# Reprocessar todos os jobs com falha
docker compose -f docker-compose.prod.yml exec php php artisan queue:retry all

# Purgar/Descartar jobs com falha irrecuperável
docker compose -f docker-compose.prod.yml exec php php artisan queue:flush
```

---

## 4. Rotinas de Backup e Restauração

### 4.1. Backup Automático Diário
Os backups do banco PostgreSQL e dos arquivos de storage são gerados pelo script `./scripts/backup.sh`:
```bash
# Execução manual ou via Cron diário às 03:00 UTC
./scripts/backup.sh
```
* O dump SQL é compactado com gzip em `/backups/database/betcrm_prod_YYYY-MM-DD_HHMMSS.sql.gz`.
* Backups com mais de 30 dias de retenção são automaticamente expurgados.

### 4.2. Procedimento de Restauração (Disaster Recovery)
Em caso de falha crítica ou corrupção de dados:
```bash
# Restauração com confirmação obrigatória
./scripts/restore.sh /backups/database/betcrm_prod_2026-10-01_030000.sql.gz
```
O script desconecta sessões ativas, recria o banco de dados e aplica o dump compactado.

---

## 5. Rotinas de Deploy e Atualizações Zero-Downtime

### 5.1. Execução de Novo Deploy
```bash
./scripts/deploy.sh
```
O script executa automaticamente:
1. Verificação de pré-requisitos (`.env.production`, Docker, Docker Compose).
2. `docker compose pull` para imagens base.
3. Build multi-stage de produção (`php` e `frontend`).
4. Execução de migrações seguras (`php artisan migrate --force`).
5. Otimização de caches (`config:cache`, `route:cache`, `view:cache`, `event:cache`).
6. Reinicialização graciosa de workers (`php artisan horizon:terminate`).
7. Validação de endpoints de saúde (`/health`, `/health/database`, `/health/redis`).

### 5.2. Rollback Emergencial
Caso o deploy apresente anomalias pós-publicação:
```bash
./scripts/rollback.sh
```

---

## 6. Observabilidade, Métricas e Logs

### 6.1. Endpoints de Monitoramento de Saúde
Os seguintes endpoints públicos/internos estão disponíveis para ferramentas como Datadog, Prometheus, Uptime Kuma ou AWS CloudWatch:

| Endpoint | Descrição | Resposta Esperada |
|---|---|---|
| `GET /health` | Liveness check geral | HTTP 200 `{"status": "ok"}` |
| `GET /health/database` | Conexão e query no PostgreSQL | HTTP 200 `{"database": "connected"}` |
| `GET /health/redis` | Ping / escrita e leitura no Redis | HTTP 200 `{"redis": "connected"}` |
| `GET /health/queue` | Estado dos workers e filas ativas | HTTP 200 `{"queue": "operational"}` |
| `GET /health/providers` | Estado e latência dos provedores | HTTP 200 `{"providers": "ready"}` |

### 6.2. Inspeção de Logs em Tempo Real
```bash
# Logs do Nginx (Acessos e Erros de Rede)
docker compose -f docker-compose.prod.yml logs -f nginx

# Logs da Aplicação Laravel (Backend)
docker compose -f docker-compose.prod.yml logs -f php

# Logs do Frontend Next.js 15
docker compose -f docker-compose.prod.yml logs -f frontend

# Visualizar arquivo de log do Laravel diretamente
docker compose -f docker-compose.prod.yml exec php tail -n 100 -f storage/logs/laravel.log
```

---

## 7. Manutenção Periódica e Limpeza de Dados

1. **Expurgo de Logs Antigos e Retenção LGPD:**
   Executado diariamente via Scheduler (`php artisan schedule:run`):
   ```bash
   # Execução manual da rotina de retenção
   docker compose -f docker-compose.prod.yml exec php php artisan privacy:retention
   ```
2. **Reconstrução de Métricas Analíticas:**
   Caso haja discrepância em métricas de campanha decorrente de instabilidade em provedores externos:
   ```bash
   docker compose -f docker-compose.prod.yml exec php php artisan campaigns:rebuild-analytics --campaign=<ID>
   ```
3. **Invalidação de Cache de Performance:**
   ```bash
   docker compose -f docker-compose.prod.yml exec php php artisan cache:clear
   docker compose -f docker-compose.prod.yml exec php php artisan analytics:clear-cache
   ```
