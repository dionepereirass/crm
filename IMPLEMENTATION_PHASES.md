# BET CRM — CRONOGRAMA DETALHADO DAS FASES DE IMPLEMENTAÇÃO

Plano estruturado em 17 fases progressivas para a construção segura, escalável e sem regressão da plataforma BET CRM.

---

## Matriz das Fases de Desenvolvimento

```
[FASE 1: Fundação & Docker] ──► [FASE 2: Auth & RBAC] ──► [FASE 3: Players & Tags]
                                                                  │
[FASE 6: Templates] ◄── [FASE 5: Segments] ◄── [FASE 4: Webhooks & Idempotência]
        │
        ▼
[FASE 7: Providers & Fakes] ──► [FASE 8: Campanhas] ──► [FASE 9: Tracking & Retornos]
                                                                  │
[FASE 12: LGPD & Opt-out] ◄── [FASE 11: Automações] ◄── [FASE 10: Relatórios & BI]
        │
        ▼
[FASE 13: Hardening & Segurança] ──► [FASE 14: Performance & Índices]
                                                   │
                                                   ▼
[FASE 17: Documentação & Go-Live] ◄── [FASE 16: Testes & QA E2E] ◄── [FASE 15: Prod & Nginx]
```

---

### FASE 1: Fundação, Repositório e Infraestrutura Base
- **Entregáveis**:
  - Estrutura de diretórios organizada (`/backend`, `/frontend`, `/docker`, `/docs`, `/tests`).
  - Ambiente Docker Compose completo (Nginx, PHP 8.3-FPM com extensões pdo_pgsql/redis/opcache, Node.js 20+, PostgreSQL 16, Redis 7).
  - Inicialização do Laravel 12 (composer install, `.env.example`, rotas base, health checks `/health`).
  - Inicialização do Next.js 15 (TypeScript, Tailwind CSS, shadcn/ui base, layout responsivo inicial).
  - Health checks de infraestrutura para PostgreSQL e Redis.
- **Critério de Aceite**: `docker compose up` sobe todos os serviços sem erro; endpoints de health check respondem com status 200 e latência do Redis/Postgres.

---

### FASE 2: Autenticação, Usuários e RBAC (Role-Based Access Control)
- **Entregáveis**:
  - Migrations e Models: `users`, `roles`, `permissions`, `role_user`, `permission_role`, `platforms`, `platform_users`.
  - Autenticação via Laravel Sanctum (login, logout, refresh, revogação de tokens).
  - Suporte a 2FA (Two-Factor Authentication com TOTP).
  - Seeders com as roles padrão: `SUPER_ADMIN`, `ADMIN`, `MARKETING`, `SUPPORT`, `ANALYST`.
  - Middleware de autorização e controle de permissões por endpoint.
  - Telas de Login, Recuperação de Senha e Layout do Dashboard com perfil do usuário logado.
- **Critério de Aceite**: Usuários autenticam com sucesso; endpoints privados bloqueiam tokens inválidos; usuários sem permissão recebem 403 Forbidden.

---

### FASE 3: Módulo de Jogadores (Players), Tags e Ficha 360°
- **Entregáveis**:
  - Migrations e Models: `players`, `player_custom_fields`, `tags`, `player_tags`.
  - CRUD completo de jogadores com isolamento por `platform_id`.
  - Paginação cursor-based e offset-based otimizada para milhões de registros.
  - Filtros dinâmicos (status, estado, cidade, afiliado, tag, período de cadastro/atividade).
  - Ficha completa do jogador no frontend exibindo dados pessoais, tags, consentimentos e histórico.
  - Linha do tempo (Timeline) inicial do jogador.
  - CRUD de tags com associação individual e em lote.
- **Critério de Aceite**: Listagem de 50.000 jogadores renderiza com paginação ágil; busca por email/external_id instantânea; tags vinculadas e desvinculadas sem N+1 queries.

---

### FASE 4: Ingestão de Webhooks, Eventos e Idempotência
- **Entregáveis**:
  - Endpoints de ingestão: `POST /api/v1/webhooks/player` e `POST /api/v1/webhooks/event`.
  - Validador de assinatura HMAC SHA-256 com base no `webhook_secret` da plataforma.
  - Camada de idempotência estrita via Redis (`SET NX EX`) e constraint no Postgres (`webhook_logs`).
  - Job assíncrono `ProcessPlayerWebhookJob` para criação/atualização não-bloqueante de jogadores.
  - Tabela `events` e disparo de eventos de domínio no Laravel.
- **Critério de Aceite**: O mesmo payload enviado 10 vezes em paralelo gera apenas 1 gravação/atualização de jogador; resposta do webhook em menos de 30ms.

---

### FASE 5: Segmentação Dinâmica e Segment Builder
- **Entregáveis**:
  - Migrations e Models: `segments`, `segment_groups`, `segment_conditions`.
  - Segment Builder visual no Next.js com suporte a blocos lógicos `AND` / `OR` e condições aninhadas.
  - `SegmentQueryCompiler`: compilador de regras AST para consultas SQL parametrizadas de alta performance no PostgreSQL.
  - Botão de cálculo prévio de audiência (Count Estimation) com cache Redis.
- **Critério de Aceite**: Regras complexas (ex: "Estado = SP E Status = ACTIVE E Dias sem Login > 15 E Consentimento SMS = SIM") executam em menos de 200ms com plano de execução otimizado (`EXPLAIN ANALYZE`).

---

### FASE 6: Templates de Mensagens (Email & SMS) e Variáveis
- **Entregáveis**:
  - Migrations e Models: `email_templates`, `sms_templates`.
  - Editor visual e HTML de templates de e-mail (botões, imagens, colunas, rodapé).
  - Editor de SMS com contador dinâmico de caracteres e cálculo de segmentos SMS (160 caracteres GSM-7).
  - Mecanismo de substituição de variáveis dinâmicas: `{{nome}}`, `{{player_id}}`, `{{cidade}}`, `{{saldo}}`, etc.
  - Pré-visualização responsiva (desktop e mobile).
- **Critério de Aceite**: Variáveis dinâmicas substituídas com precisão; mensagens com caracteres especiais (Unicode) calculadas corretamente na contagem de segmentos SMS.

---

### FASE 7: Abstração de Provedores de Mensageria (Providers)
- **Entregáveis**:
  - Contrato `MessageProviderInterface` e orquestrador `ProviderManager`.
  - Implementação de `FakeEmailProvider` e `FakeSmsProvider` para desenvolvimento e testes locais seguros.
  - Drivers reais desacoplados: `ZenviaProvider`, `BrevoProvider`, `InfobipProvider`, `SendGridProvider`.
  - `ProviderRateLimiter` com Redis Token Bucket.
  - Armazenamento de credenciais criptografadas (`provider_credentials`).
- **Critério de Aceite**: O sistema funciona 100% em modo Fake em desenvolvimento sem realizar nenhuma chamada externa real; troca de provedor por canal sem alterar regras de negócio.

---

### FASE 8: Construtor de Campanhas (Campaign Builder) e Disparo
- **Entregáveis**:
  - Migrations e Models: `campaigns`, `campaign_recipients`, `messages`.
  - Wizard de criação de campanha no Next.js (10 etapas).
  - Validação mandatória de consentimento via `CanSendMessageService`.
  - Mecanismo de envio de teste para email/telefone específico antes do disparo real.
  - Agendamento de campanhas e execução em lote via `ProcessCampaignJob` e `ProcessCampaignBatchJob`.
- **Critério de Aceite**: Campanha não dispara para jogadores com consentimento desativado ou status bloqueado; envio de teste validado antes da liberação do lote.

---

### FASE 9: Rastreamento de Mensagens e Webhooks de Provedores
- **Entregáveis**:
  - Endpoint receptor: `POST /api/v1/webhooks/providers/{provider}`.
  - Normalizador de eventos de entrega dos provedores (Zenvia, Brevo, SendGrid, Infobip e Fakes).
  - Registro de transição de status em `messages` e inserção em `message_events`.
  - Rastreamento de aberturas (pixel transparente) e cliques (redirect seguro com token assinado).
  - Tratamento de Bounces e marcação automática de contatos inválidos.
- **Critério de Aceite**: Webhook de entrega recebido atualiza atômica e instantaneamente o status da mensagem no banco e incrementa os contadores da campanha.

---

### FASE 10: Relatórios, Métricas e Dashboard Executivo
- **Entregáveis**:
  - Dashboard analítico no Next.js com cards (Total Jogadores, Ativos, Inativos, Campanhas, Entregas, Aberturas, Cliques).
  - Gráficos interativos (novos registros por dia, taxa de entrega, taxa de abertura por canal).
  - Relatório detalhado por campanha (funil de destinatários: elegíveis, enviados, entregues, abertos, clicados, falhas, descadastros).
  - Exportação assíncrona de relatórios em CSV via fila Redis.
- **Critério de Aceite**: Gráficos e números batem exatamente com as somatórias das tabelas `messages` e `players`; exportação de arquivos grandes não trava o backend.

---

### FASE 11: Motor de Automações Visuais (Workflow Engine)
- **Entregáveis**:
  - Migrations e Models: `automations`, `automation_nodes`, `automation_edges`, `automation_executions`.
  - Interface visual de montagem de fluxos (Canvas com nós arrastáveis, condições e atrasos).
  - Engine de execução de workflows assíncrono (`ProcessAutomationJob`).
  - Suporte aos nós: Trigger, Condition, Delay (10m, 24h, X dias), Email, SMS, Add Tag, Remove Tag, End.
  - Histórico de execuções auditável por jogador.
- **Critério de Aceite**: Gatilho `player.created` dispara a régua com delay programado; nós de decisão bifurcam o fluxo corretamente; logs registram cada etapa percorrida.

---

### FASE 12: Conformidade LGPD, Governança de Privacidade e Opt-Out
- **Entregáveis**:
  - Migrations e Models: `consents`, `unsubscribe_requests`.
  - Gestão de consentimentos granulares por canal (Email, SMS, WhatsApp, Push) com IP, data e versão do termo.
  - Página pública e segura de descadastro (Unsubscribe) por token assinado HMAC.
  - Endpoint para exportação dos dados do titular em JSON (Direito à Portabilidade).
  - Rotina de anonimização/exclusão lógica de dados pessoais a pedido do titular (Direito ao Esquecimento).
  - Mascaramento de CPF e telefones na interface administrativa e nas auditorias.
- **Critério de Aceite**: Jogador que solicita opt-out no link de email tem consentimento revogado imediatamente e nunca mais recebe mensagens daquele canal.

---

### FASE 13: Segurança, Hardening e Auditoria
- **Entregáveis**:
  - Configuração rigorosa de cabeçalhos HTTP (HSTS, CSP, X-Frame-Options, X-Content-Type-Options).
  - Rate limiting em rotas de autenticação, webhooks e endpoints públicos.
  - Sanitização de logs estruturados (mascaramento automático de senhas, tokens, CPFs e chaves de API).
  - Registro de auditoria completo em `audit_logs` para mutações críticas no sistema.
  - Gestão de API Keys com escopos granulares (`players:read`, `players:write`, `campaigns:read`).
- **Critério de Aceite**: Tentativas de brute force bloqueadas por rate limit; nenhum dado sensível ou chave de API vazado em logs ou respostas de erro.

---

### FASE 14: Otimização de Performance e Particionamento
- **Entregáveis**:
  - Criação de índices parciais e compostos no PostgreSQL para filtros comuns.
  - Particionamento por data da tabela `message_events` e `webhook_logs`.
  - Otimização de consultas com Eager Loading estrito e prevenção de queries N+1.
  - Otimização do pool de conexões do PostgreSQL (PgBouncer preparado).
  - Caching inteligente no Redis com invalidação orientada a eventos.
- **Critério de Aceite**: Benchmark de consultas em tabelas com 5 milhões de linhas respondendo em < 50ms; consumo de memória de workers estável.

---

### FASE 15: Infraestrutura de Produção, Nginx, SSL e Backups
- **Entregáveis**:
  - `docker-compose.prod.yml` otimizado para ambientes de produção em VPS Linux (Ubuntu 22.04/24.04).
  - Configuração do Nginx com HTTP/2, terminação SSL com Let's Encrypt / Certbot e gzip/brotli.
  - Scripts automatizados de backup diário do PostgreSQL com criptografia GPG e envio seguro para storage remoto (S3/Wasabi).
  - Script testado de restauração (Restore Verification).
  - Configurações do supervisor para workers de fila do Laravel e Laravel Horizon.
- **Critério de Aceite**: Deploy reprodutível via script único; rotina de backup executada e testada com restauração em banco limpo.

---

### FASE 16: Garantia de Qualidade, Testes Automatizados e E2E
- **Entregáveis**:
  - Suíte de testes unitários e de integração com PHPUnit / Pest no backend.
  - Testes de idempotência de webhooks com simulação de rajada simultânea de requisições.
  - Testes de regras de negócio em `CanSendMessageService` (consentimento, status, bloqueio).
  - Testes E2E com Playwright no frontend (Login, Criação de Jogador, Segmentação, Disparo de Teste de Campanha).
  - Testes de carga com k6 ou wrk para validação dos endpoints de webhook.
- **Critério de Aceite**: 100% dos testes da suíte passando; cobertura de testes em serviços críticos de mensageria superior a 90%.

---

### FASE 17: Documentação Completa e Manuais de Operação
- **Entregáveis**:
  - Manuais detalhados: `README.md`, `API.md`, `WEBHOOKS.md`, `PROVIDERS.md`, `DEPLOY.md`, `SECURITY.md`, `LGPD.md`, `QUEUE.md`, `TROUBLESHOOTING.md`.
  - Especificação OpenAPI / Swagger (`/api/documentation`) interativa para desenvolvedores externos.
  - Guia de resolução de problemas e monitoramento de falhas de envio.
- **Critério de Aceite**: Documentação clara, completa e suficiente para que qualquer engenheiro opere ou dê manutenção na plataforma sem suporte prévio.
