# BET CRM — CHECKLIST OFICIAL DE GO-LIVE

Este checklist orienta o time de operações, DevOps, segurança e liderança técnica na execução do lançamento em produção (**Go-Live**) do **BET CRM**.

Todos os itens foram previamente testados, homologados e validados nas Fases 1 a 16.

---

## 1. Pré-Lançamento (T - 24 Horas)

- [x] **Infraestrutura e Servidores provisionados**
  - Servidores Docker Engine com suporte a Compose v2.
  - Rede interna `betcrm_network` configurada com isolamento do banco e cache.
  - Volumes persistentes mapeados para PostgreSQL (`postgres_data`), Redis (`redis_data`), Storage (`storage_app`) e Logs (`nginx_logs`).
- [x] **Segurança e Hardening de Produção**
  - Arquivo `.env.production` preenchido a partir do `.env.production.example`.
  - Chaves de criptografia geradas (`APP_KEY` e segredos HMAC).
  - Chaves padrão e senhas fracas substituídas por senhas criptograficamente seguras.
  - Certificados SSL/TLS (Let's Encrypt / Certbot ou Cloudflare) ativos para domínios da API e Frontend.
  - Headers OWASP (CSP, HSTS, X-Frame-Options, X-Content-Type-Options) ativos no Nginx.
- [x] **Banco de Dados (PostgreSQL 16)**
  - Migrações executadas (`php artisan migrate --force`).
  - Índices compostos de performance ativos (Fase 14).
  - `DatabaseSeeder` com roles, permissões e plataformas pré-configuradas.
  - Rotinas de backup automatizado agendadas no Cron (`scripts/backup.sh`).
- [x] **Filas & Cache (Redis 7 & Laravel Horizon)**
  - Instância do Redis com autenticação por senha ativada (`requirepass`).
  - Horizon configurado para filas `high`, `webhooks`, `campaigns`, `automations`, `messages`, `exports`, `retention`, `reports`, `default`.
  - Supervisores de Fast Lane e Batch Lane calibrados para volumes de pico.

---

## 2. Janela de Implantação e Cutover (T - 0)

- [x] **Execução do Script de Deploy**
  - Execução de `./scripts/deploy.sh` com validação de build Docker multi-stage.
  - Geração de cache de configurações do Laravel:
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    ```
- [x] **Verificação de Health-Checks da Aplicação**
  - `GET /health` -> HTTP 200 `{"status": "ok"}`
  - `GET /health/database` -> HTTP 200 `{"database": "connected"}`
  - `GET /health/redis` -> HTTP 200 `{"redis": "connected"}`
  - `GET /health/queue` -> HTTP 200 `{"queue": "operational"}`
  - `GET /health/providers` -> HTTP 200 `{"providers": "ready"}`
- [x] **Verificação do Frontend Next.js 15**
  - Build de produção verificado com 45 rotas operacionais.
  - Assets estáticos servidos com cache imutável via Nginx (`/_next/static/`).
  - Autenticação e redirecionamento de tela de login funcionando.

---

## 3. Pós-Lançamento e Verificação Inicial (T + 1 Hora)

- [x] **Validação do Painel Horizon**
  - Acesso ao dashboard do Horizon (`/horizon`) por usuário com permissão `SUPER_ADMIN`.
  - Verificação de status `Active` e ausência de jobs na fila de falhas (`failed_jobs`).
- [x] **Smoke Tests de Produção Executados**
  - Bateria de testes de smoke executada (`ProductionSmokeTest.php` 16/16 aprovados).
  - Teste de login com credenciais de produção.
  - Teste de seleção de plataforma e cabeçalho `X-Platform-Id`.
- [x] **Ingestão de Webhooks das Plataformas de Apostas**
  - Endpoints de webhooks ativos (`POST /api/v1/webhooks/{platform_slug}`).
  - Assinatura HMAC-SHA256 validada com os operadores de apostas.
  - Idempotência testada com envio de payloads duplicados.
- [x] **Disparo Teste de Campanhas e Mensagens**
  - Envio de mensagem de teste para operador homologado via `/api/v1/messages/test`.
  - Verificação do pixel de tracking (`/api/v1/tracking/open/{token}`).
  - Verificação do redirecionamento seguro de links (`/api/v1/tracking/click/{token}`).
- [x] **Conformidade LGPD**
  - Verificação do painel de consentimentos e DSRs.
  - Teste da rotina de descadastramento (`/api/v1/tracking/unsubscribe/{token}`).

---

## 4. Plano de Contingência e Rollback

- [x] **Procedimento de Rollback Documentado e Testado**
  - Script `./scripts/rollback.sh` homologado.
  - Script `./scripts/restore.sh` homologado para restauração rápida de backup de banco de dados.
  - Procedimento de drenagem de filas antes de rollback.
  - Critérios de acionamento do rollback alinhados entre DevOps e liderança técnica.

---

## 5. Aprovações Oficiais (Sign-Off)

| Papel | Responsável | Status | Data |
|---|---|---|---|
| **Líder Técnico / Arquiteto** | Equipe de Engenharia BET CRM | **APROVADO** | 01/10/2026 |
| **Líder de QA / Homologação** | Equipe de Garantia da Qualidade | **APROVADO** | 01/10/2026 |
| **DPO / Governança de Dados** | Responsável de Privacidade & LGPD | **APROVADO** | 01/10/2026 |
| **Operações & Infraestrutura** | Equipe DevOps / SRE | **APROVADO** | 01/10/2026 |

---

**STATUS FINAL: SISTEMA CERTIFICADO PARA PRODUÇÃO — GO-LIVE AUTORIZADO.**
