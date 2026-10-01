# BET CRM — ARQUITETURA DO SISTEMA

Documento de especificação arquitetural, padrões de design, fluxo de dados e infraestrutura para a plataforma BET CRM.

---

## 1. Visão Geral da Arquitetura em Camadas

O BET CRM adota uma arquitetura em camadas desacoplada baseada nos princípios de **Clean Architecture** e **Domain-Driven Design (DDD)** adaptados ao ecossistema Laravel e Next.js. O objetivo central é manter regras de negócio estritamente isoladas do framework de entrega (HTTP / CLI / Queue), assegurando testabilidade unitária e independência de infraestrutura externa.

```
+---------------------------------------------------------------------------------------+
|                                    CAMADA CLIENTE                                    |
|   Next.js 15 (React 19 / TypeScript / Tailwind CSS / Radix UI / Zustand / React Query) |
+---------------------------------------------------------------------------------------+
                                           │
                                    REST JSON / HTTPS
                                           ▼
+---------------------------------------------------------------------------------------+
|                                   CAMADA DE ENTRADA                                   |
|   Nginx Reverse Proxy  ──►  Rate Limiting  ──►  CORS / Security Headers               |
|                                           │                                           |
|   Laravel 12 HTTP Layer:                                                              |
|     - Routes (/api/v1/*, /webhooks/*)                                                 |
|     - Middlewares (AuthenticateWithSanctum, TenantPlatformContext, WebhookSignature)   |
|     - Form Requests (Validação estrita de entrada)                                    |
|     - Controllers Finos (Apenas orquestram DTOs, chamam Actions/Services e retornam   |
|       API Resources padronizados)                                                     |
+---------------------------------------------------------------------------------------+
                                           │
                                           ▼
+---------------------------------------------------------------------------------------+
|                              CAMADA DE DOMÍNIO & SERVIÇOS                             |
|                                                                                       |
|   Data Transfer Objects (DTOs)                                                        |
|     - Imutabilidade e tipagem forte entre camadas                                      |
|                                                                                       |
|   Domain Services & Actions:                                                          |
|     - Players: RegisterPlayerAction, UpdatePlayerAction, DeduplicatePlayerService      |
|     - Messaging: CanSendMessageService, MessageDispatcherService, ProviderManager     |
|     - Campaigns: CampaignSchedulerService, CampaignEligibilityCalculatorService       |
|     - Segments: SegmentQueryCompiler (converte regras AST/JSON para SQL parametrizado) |
|     - Automations: WorkflowEngineService, StepEvaluatorService                        |
|                                                                                       |
|   Events & Listeners:                                                                 |
|     - Eventos desacoplados de domínio (PlayerCreated, CampaignStarted, MessageOpened) |
+---------------------------------------------------------------------------------------+
                    │                                               │
                    ▼                                               ▼
+---------------------------------------+       +---------------------------------------+
|          CAMADA ASSÍNCRONA            |       |        CAMADA DE PERSISTÊNCIA         |
|                                       |       |                                       |
|  Redis 7+ (Broker de Mensagens)       |       |  PostgreSQL 16+                       |
|                                       |       |                                       |
|  Laravel Horizon / Queue Workers:     |       |  Eloquent Models com:                 |
|    - default                          |       |    - PlatformScope (Multi-Tenant)     |
|    - webhooks                         |       |    - SoftDeletes                      |
|    - emails                           |       |    - UUIDv7 / BigIncrements           |
|    - sms                              |       |    - Casts & Accessors (Criptografia) |
|    - automations                      |       |                                       |
|    - reports                          |       |  Repositories & Query Builders        |
|                                       |       |  Database Migrations & Seeders        |
|  Redis Atomic Locks (Idempotência)    |       |                                       |
+---------------------------------------+       +---------------------------------------+
                    │
                    ▼
+---------------------------------------------------------------------------------------+
|                         CAMADA DE GATEWAYS & PROVEDORES                               |
|                                                                                       |
|  MessageProviderInterface (Contrato Único):                                           |
|    ├── FakeEmailProvider & FakeSmsProvider (Ambientes Locais / Testes)                |
|    ├── ZenviaProvider (SMS / WhatsApp)                                                |
|    ├── BrevoProvider (Email Transacional / Marketing)                                 |
|    ├── InfobipProvider (SMS Global)                                                   |
|    └── SendGridProvider (Email Transacional)                                          |
|                                                                                       |
|  ProviderRateLimiter: Controle granular de vazão por segundo/minuto via Redis Token   |
|  ProviderCredentialsEncryptor: Armazenamento seguro de segredos via AES-256           |
+---------------------------------------------------------------------------------------+
```

---

## 2. Padrões de Design e Estrutura de Código no Backend

### 2.1. Princípio dos Controllers Finos (Thin Controllers)
Nenhum controller do BET CRM deve conter regras de validação complexas, lógica de negócio, montagem de queries com múltiplos joins ou despacho manual de jobs.

**Responsabilidade do Controller:**
1. Receber e autorizar a requisição via Policy.
2. Injetar a `FormRequest` validada e convertê-la em um DTO tipado.
3. Invocar a Action ou Service correspondente.
4. Retornar um `JsonResource` estruturado com o código HTTP adequado (200, 201, 202, 422).

### 2.2. DTOs (Data Transfer Objects)
Todos os dados que circulam entre Controllers, Services e Jobs utilizam DTOs imutáveis com PHP 8.3 `readonly class`. Isso previne a passagem de arrays associativos genéricos sem contrato fixo.

### 2.3. Segregação Multi-Plataforma (Multi-Platform / Multi-Tenant Isolation)
O sistema foi concebido para atender múltiplos operadores de apostas em uma única infraestrutura sem risco de vazamento de dados (cross-tenant leakage).

1. **Chave de Tenant**: Toda entidade proprietária (players, campaigns, segments, messages, automations, templates, etc.) possui a coluna `platform_id` indexada.
2. **Contexto de Execução**: O middleware `TenantPlatformContext` identifica a plataforma através de:
   - Token de autenticação do usuário logado (usuários são associados via tabela `platform_users`).
   - Header de API Key em integrações externas (`X-Platform-Key` ou Bearer Token).
   - Segredo de webhook verificado via assinatura HMAC.
3. **Global Scope Automático**: Todas as Models pertinentes estendem uma trait `BelongsToPlatform`, que aplica automaticamente um `PlatformScope` ao Eloquent, garantindo que queries omitindo `platform_id` nunca retornem dados de outras plataformas, a menos que executadas explicitamente por um `SUPER_ADMIN` em modo global.

---

## 3. Arquitetura de Ingestão de Webhooks e Idempotência

Casas de apostas emitem eventos em alta frequência (registros, depósitos, logins, bloqueios). A ingestão desses webhooks precisa ser à prova de picos de carga e imune a envios duplicados.

### 3.1. Fluxo de Ingestão Rápida (Non-blocking Ingestion)
```
[Plataforma Externa]
        │
        ▼ HTTP POST /api/v1/webhooks/player
[Nginx + Laravel WebhookController]
        │
        ├── 1. Valida Assinatura HMAC (SHA-256) com webhook_secret da Plataforma
        ├── 2. Valida Presença de Header 'Idempotency-Key' ou gera hash(platform_id + external_id + event_type + timestamp)
        ├── 3. Lock Atômico no Redis: SET idempotency:{key} EX 86400 NX
        │      └── Se já existe: Retorna imediatamente HTTP 200 { status: 'already_processed' }
        ├── 4. Grava linha em 'webhook_logs' com status 'RECEIVED' e payload bruto JSONB
        ├── 5. Despacha Job 'ProcessPlayerWebhookJob' para a fila Redis 'webhooks'
        └── 6. Retorna HTTP 202 Accepted { success: true, tracking_id: "..." } em < 25ms
```

### 3.2. Processamento Assíncrono no Worker
O Job `ProcessPlayerWebhookJob` executa no worker:
1. Localiza ou cria o `Player` baseado em `(platform_id, external_id)` utilizando `upsert` com bloqueio pessimista ou controle otimista.
2. Atualiza os dados cadastrais (nome, email, telefone, status).
3. Cria o registro na tabela `events` com o tipo `player.created` ou `player.updated`.
4. Dispara o evento de domínio `PlayerCreatedEvent` ou `PlayerUpdatedEvent`, notificando os listeners de automação e segmentação.
5. Atualiza o status em `webhook_logs` para `PROCESSED`.

---

## 4. Arquitetura do Mecanismo de Disparo de Campanhas

O envio de campanhas em massa (100k a 1M+ jogadores) jamais pode sobrecarregar a memória do servidor ou bloquear o banco de dados.

```
[Operador cria Campanha no Painel]
               │
               ▼
[CampaignSchedulerService / SendCampaignAction]
               │
               ├── 1. Valida se a campanha está em status DRAFT ou SCHEDULED
               ├── 2. Atualiza status para RUNNING e registra timestamp 'started_at'
               ├── 3. Executa SegmentQueryCompiler para identificar os IDs dos players elegíveis
               ├── 4. Divide a lista de IDs em Chunks de 1.000 registros (Batch Processing)
               └── 5. Despacha 'ProcessCampaignBatchJob' para cada chunk na fila 'default'
                               │
                               ▼
               [ProcessCampaignBatchJob (Chunk de 1.000 Players)]
                               │
                               ├── 1. Carrega Players com Consentimentos e Tags via Eager Loading
                               ├── 2. Para cada Player, invoca CanSendMessageService:
                               │      ├── Status do jogador == ACTIVE?
                               │      ├── Possui consentimento explícito no canal (Email/SMS)?
                               │      ├── Canal não está desativado (sem unsubscribe)?
                               │      └── Telefone/Email no formato válido?
                               ├── 3. Se elegível:
                               │      ├── Cria registro na tabela 'messages' com status QUEUED
                               │      └── Despacha 'SendEmailJob' ou 'SendSmsJob' para a fila dedicada
                               └── 4. Se não elegível:
                                      └── Registra 'campaign_recipients' com status SKIPPED e motivo
```

### 4.1. Respeito ao Rate Limit dos Provedores (Token Bucket / Redis)
Antes de invocar o método `sendEmail()` ou `sendSms()` no provedor externo:
- O Job consulta o `ProviderRateLimiter` via Redis.
- Se o limite de requisições por segundo/minuto estiver atingido, o Job é liberado de volta na fila com delay exponencial (`$this->release($delay)`), evitando erros HTTP 429 da API externa.

---

## 5. Abstração de Provedores de Mensageria (Driver Pattern)

Nenhum código de negócio do BET CRM comunica-se diretamente com APIs de terceiros. A comunicação é mediada pelo `MessageProviderInterface`:

```php
interface MessageProviderInterface
{
    public function sendEmail(EmailMessageDTO $message): ProviderSendResultDTO;
    public function sendSms(SmsMessageDTO $message): ProviderSendResultDTO;
    public function getMessageStatus(string $providerMessageId): MessageStatusDTO;
    public function validateCredentials(array $credentials): bool;
    public function healthCheck(): HealthCheckResultDTO;
}
```

- **Ambiente de Desenvolvimento / Staging**: Por padrão, o `ProviderManager` resolve `FakeEmailProvider` e `FakeSmsProvider`, que apenas simulam o envio, geram logs estruturados e simulam webhooks de entrega e cliques sem disparar mensagens reais.
- **Ambiente de Produção**: Os drivers reais (`ZenviaProvider`, `BrevoProvider`, `InfobipProvider`, `SendGridProvider`) só são carregados quando validados e ativados via banco de dados criptografado.

---

## 6. Motor de Automações (Workflow Engine)

O construtor de automações utiliza um modelo de grafo direcionado acíclico (DAG) armazenado nas tabelas:
- `automations`: Metadados do fluxo (nome, gatilho, status ativo/pausado).
- `automation_nodes`: Passos individuais (Trigger, Condition, Delay, Action: Email, Action: SMS, Add Tag, Remove Tag).
- `automation_edges`: Conexões ordenadas entre nós com regras lógicas (ex: `branch = 'yes'` ou `branch = 'no'`).
- `automation_executions`: Rastreamento de cada jogador que entra no fluxo, ponteiro do nó atual (`current_node_id`), dados de contexto e próxima execução agendada.

**Gatilhos Suportados:**
- `player.created` (Boas-vindas)
- `player.inactive` (Reativação de apostador sem login há X dias)
- `player.active` (Apostador recorrente)
- `tag.added` / `tag.removed` (VIP, Afiliado, Campanha)
- `event.received` (Primeiro depósito, aposta realizada, etc.)

---

## 7. Arquitetura de Frontend (Next.js & shadcn/ui)

O frontend é estruturado no padrão **Feature-Driven Architecture**:

- `app/(dashboard)/`: Rotas autenticadas do painel (Jogadores, Segmentos, Campanhas, Automações, Relatórios, Configurações).
- `app/(auth)/`: Rotas públicas (Login, Redefinição de Senha, 2FA).
- `components/ui/`: Componentes atômicos shadcn/ui (Button, Dialog, Table, Form, Select, Badge, Card, Tooltip, Sheet).
- `components/features/`: Componentes ricos de negócio:
  - `campaign-wizard/`: Wizard passo a passo com estimativa de audiência em tempo real.
  - `segment-builder/`: Interface com suporte visual a árvores de condições `AND` / `OR`.
  - `automation-builder/`: Canvas de nós e conexões para montagem de réguas de relacionamento.
  - `player-timeline/`: Linha do tempo interativa e vertical dos eventos do jogador.
- `lib/api-client.ts`: Instância unificada do cliente HTTP (Axios/Fetch) configurada com interceptors para injeção de Bearer Token, renovação de sessão e tratamento amigável de erros 401, 403 e 422.
- `stores/`: Estados globais via Zustand (contexto da plataforma ativa, preferências de visualização, notificações).
