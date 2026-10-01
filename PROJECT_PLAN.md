# BET CRM — MASTER PROJECT PLAN
**Plataforma SaaS de Gestão de Clientes, Segmentação, Mensageria Multicanal e Automações para Operações de Gaming & Apostas**

---

## 1. Visão Geral e Objetivos do Produto

O **BET CRM** é uma plataforma proprietária projetada para operar no modelo SaaS multi-plataforma e multi-tenant, sem dependência de serviços externos para armazenamento, processamento e retenção de dados dos clientes (eliminando lock-in de Firebase, Supabase, Airtable, HubSpot ou Brevo).

O banco de dados relacional principal é **PostgreSQL 16+**, com camada de cache, filas e controle de concorrência em **Redis 7+**, garantindo controle 100% sob posse da infraestrutura do operador.

### Principais Pilares do BET CRM:
1. **Soberania dos Dados**: Todos os dados cadastrais, eventos de navegação, depósitos, tags, consentimentos e histórico de mensagens residem no PostgreSQL local/privado. Provedores externos (SendGrid, Brevo, Zenvia, Infobip) atuam exclusivamente como gateways transitórios de transporte de mensagens.
2. **Alta Escalabilidade e Concorrência**: Arquitetura orientada a filas assíncronas (Laravel Queue + Redis + Horizon), desenhada para suportar milhões de jogadores e disparos em lote sem degradação do painel operacional.
3. **Idempotência Estrita & Resiliência**: Webhooks de entrada e disparos de saída protegidos por chaves de idempotência, deduplicação em Redis e locks distribuídos atômicos.
4. **Conformidade Legal & LGPD**: Governança rigorosa de consentimento granular por canal (Email, SMS, WhatsApp, Push), trilha de auditoria imutável, suporte a opt-out instantâneo e mascaramento de dados sensíveis (CPF, telefone, email).
5. **Multi-Plataforma & Multi-Tenant**: Isolamento lógico de dados por `platform_id` através de Global Scopes, Policies, Middleware e Service Layers, viabilizando que uma mesma instância atenda múltiplas marcas/casas de aposta com total segregação.

---

## 2. Escopo Funcional do Sistema

```
+------------------------------------------------------------------------------------+
|                                     BET CRM                                        |
+------------------------------------------------------------------------------------+
|  1. Módulo de Plataformas & Multi-Tenancy                                          |
|     - Gestão de Platforms (marcas/casas), API Keys e Segredos de Webhook           |
|                                                                                    |
|  2. Módulo de Autenticação & RBAC                                                  |
|     - Sanctum (Tokens de Sessão e API), 2FA, Roles: SUPER_ADMIN, ADMIN,            |
|       MARKETING, SUPPORT, ANALYST                                                  |
|                                                                                    |
|  3. Módulo de Jogadores (Players)                                                  |
|     - CRUD, External ID Mapping, Ficha 360°, Timeline de Ações, Tags,              |
|       Custom Fields (JSONB), Importação CSV em background, Busca & Paginação       |
|                                                                                    |
|  4. Módulo de Ingestão de Webhooks (Entrada)                                       |
|     - Endpoints de alta velocidade (/webhooks/player, /webhooks/event),            |
|       Validação de HMAC SHA-256, Idempotência com Redis/Postgres, Despacho Jobs   |
|                                                                                    |
|  5. Módulo de Segmentação Dinâmica                                                 |
|     - Segment Builder visual com operadores AND/OR e grupos aninhados,             |
|       Avaliação de regras em SQL otimizado com índices GIN/B-Tree                  |
|                                                                                    |
|  6. Módulo de Templates                                                            |
|     - Email Template Builder (HTML, componentes visuais, variáveis dinâmicas)      |
|     - SMS Template Builder (Contador de caracteres, contagem de partes SMS)        |
|                                                                                    |
|  7. Abstração de Provedores de Mensageria                                          |
|     - MessageProviderInterface unificada (Zenvia, Brevo, Infobip, SendGrid,        |
|       FakeEmailProvider, FakeSmsProvider para dev/testes)                          |
|     - Provider Rate Limiter configurável e failover estruturado                    |
|                                                                                    |
|  8. Módulo de Campanhas                                                            |
|     - Wizard 10 etapas, Pré-visualização com cálculo de elegibilidade e consent,   |
|       Modo de envio de teste prévio, Agendamento e Disparo assíncrono em batch     |
|                                                                                    |
|  9. Rastreamento de Mensagens & Webhooks de Provedores                             |
|     - Ingestão de eventos de entrega (sent, delivered, opened, clicked, bounced), |
|       Atualização atômica de status de mensagens e métricas                        |
|                                                                                    |
| 10. Módulo de Automações (Workflows)                                               |
|     - Motor de workflow por grafo (Triggers, Conditions, Delays, Actions),         |
|       Execução confiável via filas e histórico de execuções auditado               |
|                                                                                    |
| 11. Governança LGPD & Auditoria                                                    |
|     - CanSendMessageService (Validação mandatória de consentimento antes do envio) |
|     - Gestão de Opt-Out/Unsubscribe e Trilha de Auditoria detalhada                |
|                                                                                    |
| 12. Dashboard Operacional & Relatórios                                             |
|     - Métricas em tempo real, taxas de conversão/entrega/abertura,                 |
|       Exportação assíncrona em CSV/Excel                                           |
+------------------------------------------------------------------------------------+
```

---

## 3. Matriz de Decisões Tecnológicas

| Componente | Tecnologia Escolhida | Justificativa Técnica |
| :--- | :--- | :--- |
| **Backend Core** | Laravel 12 (PHP 8.3+) | Ecossistema maduro, suporte nativo a Jobs, Horizon, Sanctum, Eloquent com suporte avançado a JSONB do PostgreSQL e tipagem estrita no PHP 8.3. |
| **Frontend SPA/SSR** | Next.js 15 (App Router, React 19, TypeScript) | SSR/SSG otimizado para painel administrativo, segurança de tipagem de ponta a ponta e alta performance de renderização. |
| **Biblioteca de UI** | Tailwind CSS + shadcn/ui (Radix UI) | Acessibilidade nativa, componentes desacoplados sem dependência pesada de runtime, alta customização e design corporativo moderno. |
| **Banco Relacional** | PostgreSQL 16 | Suporte robusto a particionamento nativo de tabelas, índices GIN em campos JSONB, UUIDs v7 ordenáveis e integridade referencial ACID estrita. |
| **Cache & Filas** | Redis 7+ | Baixíssima latência para locks atômicos (`SET NX EX`), filas priorizadas, Rate Limiting distribuído e cache de sessões/idempotência. |
| **Orquestrador Filas** | Laravel Horizon | Dashboard nativo para monitoramento de throughput de jobs, balanceamento de workers, controle de métricas de filas e alertas de falhas. |
| **Proxy Reverso / Web** | Nginx | Terminação TLS/SSL eficiente, compressão Brotli/Gzip, isolamento de rotas estáticas vs `/api` e buffers para proteção anti-DDoS. |
| **Isolamento Ambientes** | Docker & Docker Compose | Reprodutibilidade idêntica entre desenvolvimento, homologação (staging) e produção VPS. |

---

## 4. Diretrizes Inegociáveis de Engenharia

1. **Nunca executar operações pesadas no ciclo de requisição HTTP**: Qualquer importação de arquivo, avaliação de segmentos massivos, envio de campanhas ou processamento de webhooks deve responder imediatamente e despachar um Job assíncrono para a fila do Redis.
2. **Zero Mensagens Reais em Desenvolvimento**: Todo ambiente fora de `APP_ENV=production` opera obrigatoriamente com os drivers `FakeEmailProvider` e `FakeSmsProvider`, registrando as transições de estado simuladas no banco de dados local.
3. **Isolamento de Segurança**: Nenhuma chave privada, token ou senha trafega em texto puro ou é versionada no Git. Credenciais de provedores cadastradas no banco são criptografadas com `Laravel Crypt` (AES-256-CBC / AES-256-GCM).
4. **Preservação de Dados & Zero Migrations Destrutivas**: Em qualquer fase de evolução, migrations devem adicionar colunas ou tabelas com retrocompatibilidade, nunca removendo campos ou executando truncates/drops sem processo formal de migração de dados em 2 etapas.
5. **Auditoria de Cada Mutação**: Toda alteração cadastral, criação de campanha, alteração de status ou troca de credencial gera registro na tabela `audit_logs` com snapshot do antes e depois.
