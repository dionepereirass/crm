# BET CRM — Enterprise SaaS Platform

Plataforma própria, escalável e de alto desempenho para gestão de clientes, segmentação dinâmica, automação e mensageria multicanal (E-mail, SMS, WhatsApp e Push) voltada para operações de apostas e gaming.

---

## 📚 Documentação do Projeto

- [Plano do Projeto (PROJECT_PLAN.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/PROJECT_PLAN.md)
- [Arquitetura de Software e Decisões Técnicas (ARCHITECTURE.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/ARCHITECTURE.md)
- [Modelagem do Banco de Dados e Índices (DATABASE_PLAN.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/DATABASE_PLAN.md)
- [Relatório de Conclusão da Fase 1 (docs/FASE_1_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_1_REPORT.md)
- [Relatório de Conclusão da Fase 2 (docs/FASE_2_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_2_REPORT.md)
- [Relatório de Conclusão da Fase 3 (docs/FASE_3_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_3_REPORT.md)
- [Relatório de Conclusão da Fase 4 (docs/FASE_4_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_4_REPORT.md)
- [Relatório de Conclusão da Fase 5 (docs/FASE_5_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_5_REPORT.md)
- [Relatório de Conclusão da Fase 6 (docs/FASE_6_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_6_REPORT.md)
- [Relatório de Conclusão da Fase 7 (docs/FASE_7_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_7_REPORT.md)
- [Relatório de Conclusão da Fase 8 (docs/FASE_8_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_8_REPORT.md)
- [Relatório de Conclusão da Fase 9 (docs/FASE_9_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_9_REPORT.md)
- [Relatório de Conclusão da Fase 10 (docs/FASE_10_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_10_REPORT.md)
- [Relatório de Conclusão da Fase 11 (docs/FASE_11_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_11_REPORT.md)
- [Relatório de Conclusão da Fase 15 (docs/FASE_15_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_15_REPORT.md)
- [Relatório Final de QA e Homologação (docs/FINAL_QA.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FINAL_QA.md)
- [Checklist Oficial de Go-Live (docs/GO_LIVE_CHECKLIST.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/GO_LIVE_CHECKLIST.md)
- [Manual de Operações e Runbook (docs/OPERATIONS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/OPERATIONS.md)
- [Relatório de Conclusão da Fase 16 (docs/FASE_16_REPORT.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_16_REPORT.md)
- [Guia de Integração de Webhooks (docs/WEBHOOK_INTEGRATION.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/WEBHOOK_INTEGRATION.md)
- [Manual do Motor de Segmentação (docs/SEGMENTATION_ENGINE.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/SEGMENTATION_ENGINE.md)
- [Sistema de Templates de Comunicação (docs/TEMPLATES.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/TEMPLATES.md)
- [Guia de Provedores e Mensageria (docs/PROVIDERS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/PROVIDERS.md)
- [Sistema de Campanhas de Comunicação (docs/CAMPAIGNS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/CAMPAIGNS.md)
- [Tracking, Eventos e Analytics Operacional (docs/TRACKING_ANALYTICS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/TRACKING_ANALYTICS.md)
- [Motor de Automações e Jornadas (docs/AUTOMATIONS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/AUTOMATIONS.md)
- [Política de Privacidade e Sanitização (docs/PRIVACY.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/PRIVACY.md)
- [Conformidade LGPD e Direitos do Titular (docs/LGPD.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/LGPD.md)
- [Governança de Dados e Políticas de Retenção (docs/DATA_GOVERNANCE.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/DATA_GOVERNANCE.md)
- [Analytics, Dashboards e Inteligência Operacional (docs/ANALYTICS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/ANALYTICS.md)
- [Motor de Relatórios e Exportação (docs/REPORTS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/REPORTS.md)
- [Motor de Alertas e Incidentes Operacionais (docs/ALERTS.md)](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/ALERTS.md)

---

## 🚀 Como Executar o Projeto (FASE 1)

### Opção 1: Via Docker Compose (Recomendado)
```bash
docker compose up -d
```
- Painel Web (Frontend): `http://localhost:3000`
- API & Nginx: `http://localhost`
- Health Check: `http://localhost/health`

### Opção 2: Localmente via Scripts
```powershell
# Iniciar Backend e Frontend em janelas separadas
.\scripts\dev.ps1

# Executar a suíte de testes
.\scripts\test.ps1
```

---

## 🛠️ Stack Tecnológica

- **Backend**: Laravel 12 (PHP 8.3+)
- **Frontend**: Next.js 15 (React 19, TypeScript, Tailwind CSS, shadcn/ui)
- **Banco de Dados**: PostgreSQL 16+
- **Cache & Filas**: Redis 7+ e Laravel Horizon
- **Servidor Web**: Nginx
- **Contêineres**: Docker & Docker Compose
- **Autenticação**: Laravel Sanctum (Tokens de Sessão e API com RBAC)

---

## 📁 Estrutura de Diretórios

```
/
├── backend/                  # Aplicação Laravel 12
│   ├── app/
│   │   ├── Actions/          # Ações atômicas de negócio
│   │   ├── DTOs/             # Data Transfer Objects com tipagem forte
│   │   ├── Enums/            # Enums de domínio (Status, Canais, etc.)
│   │   ├── Http/             # Controllers finos, FormRequests e Resources
│   │   ├── Jobs/             # Trabalhos assíncronos de fila
│   │   ├── Models/           # Modelos Eloquent com PlatformScope
│   │   ├── Policies/         # Autorização e isolamento multi-tenant
│   │   └── Services/         # Lógica de negócio (Campanhas, Segmentos, etc.)
│   ├── database/             # Migrations, seeders e factories
│   └── routes/               # Rotas de API e Web
├── frontend/                 # Aplicação Next.js 15
│   ├── app/                  # App Router
│   ├── components/           # Componentes UI (shadcn) e Features ricas
│   ├── hooks/                # Custom React Hooks
│   ├── lib/                  # Utilitários e cliente API
│   ├── services/             # Chamadas de API tipadas
│   ├── stores/               # Gerenciamento de estado (Zustand)
│   └── types/                # Definições TypeScript
├── docker/                   # Configurações Docker (Nginx, PHP-FPM)
├── docs/                     # Documentação complementar do sistema
├── tests/                    # Suíte de testes (Unitários, Integração e E2E)
├── docker-compose.yml        # Orquestração local dos serviços
└── .env.example              # Modelo de variáveis de ambiente
```

---

## 🔒 Princípios de Segurança e Desenvolvimento (Fase 13 Hardening)

1. **Soberania dos Dados**: Dados dos apostadores permanecem 100% no PostgreSQL privado; serviços externos funcionam unicamente como canais de transporte.
2. **Defesa em Profundidade (OWASP Top 10)**: Headers de segurança automáticos (`nosniff`, `SAMEORIGIN`, CSP, `Permissions-Policy`, remoção de `X-Powered-By`), anti-XSS e bloqueio de arquivos sensíveis no Nginx.
3. **Prevenção contra Injeção de Fórmulas CSV**: Células com `=`, `+`, `-`, `@`, `\t` ou `\r` são prefixadas com `'` para neutralizar ataques DDE/Formula Injection em planilhas.
4. **Rate Limiting Granular**: Limitação de requisições em exportações (10/min), webhooks (300/min), disparos de teste (20/min), login (lockout) e API global (600/min).
5. **Webhooks Criptográficos**: Assinatura HMAC-SHA256 validada com `hash_equals()`, proteção contra replay attacks via timestamp (5 min) e proteção DoS contra payloads > 512KB.
6. **Zero Secrets no Git**: Todas as credenciais são mantidas em variáveis de ambiente ou criptografadas no banco via AES-256. Redação automática de segredos em logs (`***REDACTED***`).
7. **Isolamento Multi-Plataforma**: Cada entidade possui `platform_id` indexado, assegurando isolamento lógico rigoroso contra IDOR / BOLA entre marcas.
8. **Documentação de Segurança**: Consulte [docs/SECURITY.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/SECURITY.md) e [docs/FASE_13_REPORT.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_13_REPORT.md).

---

## ⚡ Performance, Escalabilidade e Otimização (Fase 14)

1. **Índices Compostos e de Cobertura**: Índices dedicados para padrões multi-tenant de alto volume (`messages`, `message_events`, `events`, `players`, `campaign_recipients`, `automation_runs`, `audit_logs`).
2. **Cache de Contexto Multi-Tenant**: Resolução de plataformas por ID e Slug armazenada no Redis por 1h com invalidação automática orientada a eventos do Eloquent (`saved`, `deleted`).
3. **Memoização de Analytics**: Cache inteligente de KPIs e dashboards executivos (`betcrm:analytics:{platform}:{metric}:{hash}`) com TTL adaptativo e invalidação granular.
4. **Filas Balanceadas no Laravel Horizon**: Priorização dinâmica com supervisores dedicados cobrindo `webhooks`, `events`, `messages`, `campaigns`, `automations`, `analytics`, `reports`.
5. **Processamento em Lotes por Cursor (`chunkById`)**: Eliminação de estouros de memória em campanhas com centenas de milhares de jogadores através de streaming em lotes e verificação contínua de status em tempo real.
6. **Documentação de Performance**: Consulte [docs/PERFORMANCE.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/PERFORMANCE.md) e [docs/FASE_14_REPORT.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_14_REPORT.md).

---

## 🚀 Infraestrutura, Deploy e Preparação para Produção (Fase 15)

1. **Docker de Produção Isolado**: Orquestração multi-container via [`docker-compose.prod.yml`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docker-compose.prod.yml) com PostgreSQL e Redis operando exclusivamente em rede interna privada (`data_net`), sem portas expostas ao host.
2. **Nginx com HTTPS e HSTS**: Configuração de produção com terminação SSL/TLS, HTTP/2, HSTS (`max-age=31536000`), suporte a ACME/Certbot e bloqueio de arquivos ocultos e sensíveis.
3. **Automação de Backup e Restore**: Scripts automatizados [`scripts/backup.sh`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/scripts/backup.sh) (dumps diários comprimidos com expurgo de 14 dias) e [`scripts/restore.sh`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/scripts/restore.sh).
4. **Pipeline de Deploy e Rollback**: Scripts operacionais [`scripts/deploy.sh`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/scripts/deploy.sh) e [`scripts/rollback.sh`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/scripts/rollback.sh) com verificações prévias, backup preventivo e migrations forçadas.
5. **Monitoramento e Observabilidade**: Manual operacional em [`docs/MONITORING.md`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/MONITORING.md) cobrindo CPU, RAM, Disk, Postgres, Redis e filas no Horizon.
6. **Bateria de Smoke Tests**: Teste automatizado cobrindo os 16 subsistemas críticos em [`ProductionSmokeTest.php`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/backend/tests/Feature/ProductionSmokeTest.php).
7. **Manuais de Produção**: Consulte [docs/PRODUCTION.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/PRODUCTION.md), [docs/DEPLOY.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/DEPLOY.md), [docs/ROLLBACK.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/ROLLBACK.md) e [docs/FASE_15_REPORT.md](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FASE_15_REPORT.md).

---

## 🎯 QA Final, Homologação e Go-Live (Fase 16)

1. **Bateria Completa de Regressão**: **241 testes automatizados (1.095 assertions) com 100% de sucesso (GREEN)**, cobrindo Unit, Feature, Smoke, Hardening, Performance e E2E.
2. **Homologação E2E de Negócio**: Testes de ponta a ponta em [`FinalHomologationE2ETest.php`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/backend/tests/Feature/FinalHomologationE2ETest.php) validando todo o ciclo operacional (`Platform -> Player -> Event -> Segment -> Template -> Provider -> Campaign -> Message -> Tracking -> Analytics`).
3. **Frontend Otimizado para Produção**: **45 rotas compiladas** sem nenhum erro TypeScript (`strict`) ou avisos de ESLint.
4. **Isolamento e Conformidade Multi-Tenant**: Barreira estrita de dados validada entre múltiplas plataformas e matriz RBAC integralmente respeitada por todos os 5 papéis.
5. **Certificação Go-Live**: Consulte o relatório oficial em [`docs/FINAL_QA.md`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/FINAL_QA.md), o checklist em [`docs/GO_LIVE_CHECKLIST.md`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/GO_LIVE_CHECKLIST.md) e o runbook em [`docs/OPERATIONS.md`](file:///x:/OPUSS%20DIGITAL/OPUSS%20CRM/docs/OPERATIONS.md).




