# BET CRM — Tracking, Eventos de Entrega e Analytics

Este documento descreve a arquitetura, o fluxo de dados, as especificações técnicas e as diretrizes de governança do subsistema de **Tracking, Eventos de Entrega e Analytics Operacional de Campanhas** do BET CRM.

---

## 1. Visão Geral da Arquitetura

O módulo de Tracking & Analytics fecha o ciclo de mensageria operacional do BET CRM, integrando os dados gerados desde a criação de campanhas até o comportamento individual dos destinatários:

```text
Campanha Disparada
       ↓
MessageService::send()
       ↓ (TrackingProcessor injeta pixel + reescreve links + unsubscribe)
Mensagem Persistida & Enfileirada
       ↓
Provedor de Mensageria (Brevo / Zenvia / Fake)
       ↓
Disparo ao Destinatário
       ↓
Eventos de Engajamento / Resposta do Provedor
 ┌────────────────────────────────────────────────────────┐
 │ - Webhook de Provedor (SENT, DELIVERED, BOUNCE, etc.) │
 │ - Tracking Pixel (/api/v1/tracking/open/{token})       │
 │ - Link Redirect   (/api/v1/tracking/click/{token})      │
 │ - Unsubscribe     (/api/v1/tracking/unsubscribe/{tok}) │
 └────────────────────────────────────────────────────────┘
       ↓
MessageEventService::recordEvent()
       ↓ (Deduplicação, Precedência de Status, LGPD)
MessageEvent Persistido & Status da Mensagem Atualizado
       ↓
ProcessMessageEventJob (Queue: `analytics`)
       ↓
CampaignAnalyticsService::recordEvent()
       ↓
Tabela Agregada `campaign_metrics` Atualizada (Event Sourcing Reconstruível)
```

---

## 2. Ingestão e Processamento de Tracking

### 2.1. Injeção de Rastreamento em E-mails (`TrackingProcessor`)
Para canais de e-mail com rastreamento habilitado:
1. **Pixel de Abertura**:
   - Um elemento invisível `<img src=".../api/v1/tracking/open/{token}" width="1" height="1" ... alt="" />` é injetado imediatamente antes da tag `</body>` (ou anexado ao final do HTML).
   - O endpoint responde com um GIF transparente de 1x1 byte (`47 49 46 38 39 61 01 00 01 00 ...`) com cabeçalhos HTTP estritos de proibição de cache (`no-cache, no-store, must-revalidate`).
2. **Reescrita de Links (Click Tracking)**:
   - Todas as tags `<a href="...">` contendo URLs HTTP/HTTPS válidas têm seu atributo `href` substituído por `.../api/v1/tracking/click/{link_token}`.
   - Os links originais são armazenados de forma imutável na tabela `message_links` com contador de cliques e carimbo de data/hora do primeiro clique.
   - O redirecionamento implementa proteção contra **Open Redirect**, validando o protocolo web e retornando status 302 direcionado exclusivamente à URL original registrada.
3. **Opt-out / Unsubscribe LGPD**:
   - Se o template contiver o marcador `{{unsubscribe_url}}`, ele é substituído pelo link `/api/v1/tracking/unsubscribe/{token}`.
   - Se não contiver, um rodapé padrão em conformidade com as diretrizes da LGPD é automaticamente anexado ao corpo da mensagem.
   - Ao acessar o link, o consentimento de marketing do jogador é revogado atomicamente (`marketing_consent = false`) na plataforma correspondente e registrado na trilha de auditoria do jogador.

---

## 3. Webhooks de Provedores e Normalização

### 3.1. Endpoint Unificado
- Rota: `POST /api/v1/providers/webhooks/{driver}`
- Drivers Suportados: `brevo`, `zenvia`, `fake`, `generic`

### 3.2. Normalização (`ProviderEventNormalizer`)
Diferentes provedores emitem terminologias distintas para o ciclo de vida da mensagem:
- **Brevo**: `sent` → `SENT`, `delivered` → `DELIVERED`, `opened` / `unique_opened` → `OPENED`, `click` → `CLICKED`, `soft_bounce` / `hard_bounce` → `BOUNCED`, `spam` / `blocked` → `REJECTED`, `unsubscribe` → `UNSUBSCRIBED`.
- **Zenvia**: `MESSAGE_SENT` → `SENT`, `MESSAGE_DELIVERED` → `DELIVERED`, `MESSAGE_NOT_DELIVERED` → `FAILED`.

### 3.3. Idempotência e Prevenção de Downgrade
1. **Idempotência**: Se o evento contiver `provider_event_id`, ele é checado em `message_events`. Na ausência de ID nativo, um hash SHA-256 determinístico dos atributos chave é calculado. Eventos duplicados são descartados com status HTTP 200 informativo.
2. **Precedência de Status**: Garante que eventos atrasados ou fora de ordem não regridam o status da mensagem.
   - Hierarquia: `QUEUED (10)` < `SENDING (20)` < `SENT (30)` < `DELIVERED (40)` < `OPENED (50)` < `CLICKED (60)`. Estados terminais de falha (`FAILED`, `BOUNCED`, `REJECTED`) possuem precedência máxima (70).

---

## 4. Agregação e Métricas de Campanhas

### 4.1. Tabela Agregada `campaign_metrics`
Para viabilizar consultas analíticas ultra-rápidas sem sobrecarregar a tabela de eventos, os totais e taxas são mantidos na tabela `campaign_metrics`:
- **Volumes Brutos**: `total_recipients`, `total_messages`, `sent_count`, `delivered_count`, `failed_count`, `bounced_count`, `rejected_count`, `opened_count`, `unique_opened_count`, `clicked_count`, `unique_clicked_count`, `unsubscribed_count`.
- **Taxas Calculadas**:
  - `delivery_rate`: $\frac{\text{delivered}}{\text{sent}} \times 100$
  - `open_rate`: $\frac{\text{unique\_opened}}{\text{delivered}} \times 100$
  - `click_rate`: $\frac{\text{unique\_clicked}}{\text{delivered}} \times 100$
  - `click_to_open_rate` (CTOR): $\frac{\text{unique\_clicked}}{\text{unique\_opened}} \times 100$
  - `bounce_rate`: $\frac{\text{bounced}}{\text{sent}} \times 100$
  - `unsubscribe_rate`: $\frac{\text{unsubscribed}}{\text{delivered}} \times 100$
- Proteção contra divisão por zero garantida em todos os cálculos.

### 4.2. Reconstrução de Analytics (Event Sourcing)
As métricas agregadas podem ser inteiramente reconstruídas a partir da verdade dos fatos (`message_events` e `campaign_recipients`) a qualquer momento via CLI ou API:
```bash
php artisan campaigns:rebuild-analytics --campaign=1
php artisan campaigns:rebuild-analytics --platform=1
php artisan campaigns:rebuild-analytics
```

---

## 5. Endpoints de API Disponibilizados

| Método | Endpoint | Permissão | Descrição |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/tracking/open/{token}` | Pública (Rate Limited) | Registra abertura e retorna pixel transparente |
| `GET` | `/api/v1/tracking/click/{token}` | Pública (Rate Limited) | Registra clique e redireciona para URL original |
| `GET` | `/api/v1/tracking/unsubscribe/{token}` | Pública (Rate Limited) | Revoga consentimento LGPD e exibe confirmação |
| `POST` | `/api/v1/providers/webhooks/{driver}` | Pública / Webhook Secret | Ingestão e normalização de eventos de provedores |
| `GET` | `/api/v1/campaigns/{id}/analytics` | `analytics.view` | Métricas consolidadas, taxas, funil e provedores |
| `GET` | `/api/v1/campaigns/{id}/events` | `analytics.view` | Trilha cronológica de eventos com paginação |
| `POST` | `/api/v1/campaigns/{id}/analytics/rebuild` | `campaigns.manage` | Dispara reconstrução atômica das métricas |
| `GET` | `/api/v1/analytics/overview` | `analytics.view` | Visão geral da plataforma e KPIs globais |
| `GET` | `/api/v1/analytics/campaigns` | `analytics.view` | Tabela comparativa de performance por campanha |
| `GET` | `/api/v1/analytics/campaigns/export` | `analytics.export` | Exportação de relatório operacional em CSV |

---

## 6. Governança e LGPD

1. **Privacidade de Dados**: Dados sensíveis de jogadores não são expostos em links de tracking. Apenas tokens alfanuméricos randômicos de 64 caracteres criptograficamente seguros são utilizados.
2. **Minimização de Logs de Acesso**: Endereços IP são truncados ou anonimizados e User-Agents são gravados apenas para detecção de clientes de e-mail / bots.
3. **Isolamento Multi-Tenant**: Consultas analíticas aplicam obrigatoriamente o escopo global da plataforma autenticada (`BelongsToPlatform`), impedindo qualquer vazamento de métricas entre operadores.
