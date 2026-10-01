# BET CRM — PLANO DE BANCO DE DADOS (POSTGRESQL 16+)

Especificação detalhada de esquemas relacionais, tipos de dados, chaves primárias/estrangeiras, índices de alta performance e estratégia de particionamento para suportar milhões de jogadores e eventos.

---

## 1. Diretrizes de Modelagem de Dados

1. **Chaves Primárias e Identificadores**:
   - Chaves relacionais internas usam `bigserial` ou `uuid v7` (ordenável cronologicamente) para performance de inserção e indexação em árvores B-tree.
   - Todo jogador possui `platform_id` e `external_id`, com restrição de unicidade composta `UNIQUE (platform_id, external_id)`.
2. **Campos Customizados e Flexibilidade**:
   - `player_custom_fields`: Armazenados via coluna `jsonb` indexada por GIN (`CREATE INDEX ON players USING gin(custom_fields)`), permitindo filtros dinâmicos sem alterar o schema do banco.
3. **Imutabilidade e Soft Deletes**:
   - Entidades críticas como `players`, `campaigns`, `segments`, `email_templates`, `sms_templates` e `automations` utilizam Soft Deletes (`deleted_at TIMESTAMP WITH TIME ZONE NULL`).
   - Tabelas de auditoria e telemetria (`audit_logs`, `events`, `message_events`, `webhook_logs`) são append-only (sem atualização ou deleção).
4. **Particionamento de Tabelas de Alto Volume**:
   - As tabelas `message_events`, `events`, `messages` e `webhook_logs` são planejadas para particionamento nativo por intervalo de datas (`RANGE (created_at)` mensal), evitando degradação de índices com dezenas de milhões de linhas.

---

## 2. Dicionário Completo de Tabelas

### 2.1. Plataformas e Multi-Tenancy

#### `platforms`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador interno |
| `uuid` | `uuid` | `NOT NULL UNIQUE` | UUID v7 para referência externa |
| `name` | `varchar(150)` | `NOT NULL` | Nome da marca / plataforma de apostas |
| `slug` | `varchar(150)` | `NOT NULL UNIQUE` | Slug identificador (ex: 'bet-brasil') |
| `status` | `varchar(30)` | `NOT NULL DEFAULT 'ACTIVE'` | ACTIVE, INACTIVE, SUSPENDED |
| `api_key` | `varchar(255)` | `NOT NULL UNIQUE` | Chave de API única da plataforma |
| `webhook_secret` | `varchar(255)`| `NOT NULL` | Segredo para assinatura HMAC SHA-256 |
| `settings` | `jsonb` | `NULL` | Configurações específicas em formato JSONB |
| `created_at` | `timestamptz` | `NOT NULL` | Data de criação |
| `updated_at` | `timestamptz` | `NOT NULL` | Data de atualização |

#### `platform_users`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador interno |
| `platform_id` | `bigint` | `NOT NULL REFERENCES platforms(id) ON DELETE CASCADE` | Plataforma vinculada |
| `user_id` | `bigint` | `NOT NULL REFERENCES users(id) ON DELETE CASCADE` | Usuário vinculado |
| `created_at` | `timestamptz` | `NOT NULL` | Data de vínculo |
| **Índices** | `UNIQUE(platform_id, user_id)` | | Previne vínculos duplicados |

---

### 2.2. Autenticação e Controle de Acesso (RBAC)

#### `users`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador do usuário |
| `name` | `varchar(255)` | `NOT NULL` | Nome completo |
| `email` | `varchar(255)` | `NOT NULL UNIQUE` | Email para login |
| `password` | `varchar(255)` | `NOT NULL` | Hash da senha (bcrypt/argon2) |
| `two_factor_secret` | `text` | `NULL` | Segredo TOTP (criptografado) |
| `two_factor_recovery_codes` | `text` | `NULL` | Códigos de recuperação 2FA |
| `two_factor_confirmed_at` | `timestamptz` | `NULL` | Data de confirmação do 2FA |
| `status` | `varchar(30)` | `NOT NULL DEFAULT 'ACTIVE'` | ACTIVE, INACTIVE, BLOCKED |
| `remember_token` | `varchar(100)` | `NULL` | Token de sessão persistente |
| `created_at` | `timestamptz` | `NOT NULL` | Data de cadastro |
| `updated_at` | `timestamptz` | `NOT NULL` | Data de alteração |
| `deleted_at` | `timestamptz` | `NULL` | Soft delete |

#### `roles`
`id`, `name` (SUPER_ADMIN, ADMIN, MARKETING, SUPPORT, ANALYST), `slug`, `description`, `created_at`, `updated_at`.

#### `permissions`
`id`, `name`, `slug` (ex: `players.view`, `players.export`, `campaigns.send`), `group`, `created_at`, `updated_at`.

#### `role_user` & `permission_role`
Tabelas de relacionamento associativo N:N com chaves estrangeiras compostas e exclusão em cascata.

---

### 2.3. Módulo de Jogadores (Players) e Tags

#### `players`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador interno |
| `platform_id` | `bigint` | `NOT NULL REFERENCES platforms(id) ON DELETE RESTRICT` | Plataforma proprietária |
| `external_id` | `varchar(100)` | `NOT NULL` | ID do jogador no backend do operador |
| `name` | `varchar(255)` | `NOT NULL` | Nome completo |
| `email` | `varchar(255)` | `NULL` | Endereço de email |
| `phone` | `varchar(30)` | `NULL` | Telefone internacional (E.164) |
| `whatsapp` | `varchar(30)` | `NULL` | Número de WhatsApp validado |
| `cpf` | `varchar(20)` | `NULL` | CPF (mascarado nas listagens da UI) |
| `birth_date` | `date` | `NULL` | Data de nascimento |
| `gender` | `varchar(20)` | `NULL` | Gênero |
| `city` | `varchar(100)` | `NULL` | Cidade |
| `state` | `varchar(10)` | `NULL` | UF / Estado |
| `zip_code` | `varchar(20)` | `NULL` | CEP / Código Postal |
| `status` | `varchar(30)` | `NOT NULL DEFAULT 'ACTIVE'` | ACTIVE, INACTIVE, BLOCKED, PENDING, DELETED |
| `source` | `varchar(100)` | `NULL` | Origem do cadastro (ex: organic, affiliate, adwords) |
| `affiliate` | `varchar(100)` | `NULL` | Código ou nome do afiliado |
| `promo_code` | `varchar(100)` | `NULL` | Cupom / código promocional de registro |
| `custom_fields` | `jsonb` | `NOT NULL DEFAULT '{}'` | Metadados extras arbitrários |
| `last_login_at` | `timestamptz` | `NULL` | Timestamp do último login |
| `last_activity_at` | `timestamptz` | `NULL` | Timestamp da última aposta/depósito |
| `registered_at` | `timestamptz` | `NULL` | Data de registro original na casa |
| `created_at` | `timestamptz` | `NOT NULL` | Criação no CRM |
| `updated_at` | `timestamptz` | `NOT NULL` | Atualização no CRM |
| `deleted_at` | `timestamptz` | `NULL` | Soft delete |
| **Índices** | `UNIQUE (platform_id, external_id)` | | Garante unicidade do jogador por operador |
| | `INDEX (platform_id, status)` | | Otimiza listagens filtradas |
| | `INDEX (platform_id, email)` | | Busca rápida por email |
| | `INDEX (platform_id, phone)` | | Busca rápida por telefone |
| | `INDEX (platform_id, state)` | | Filtros regionais rápidos |
| | `INDEX (platform_id, last_activity_at)` | | Segmentação de inatividade |
| | `INDEX USING gin (custom_fields)` | | Consultas dinâmicas em JSONB |

#### `tags` & `player_tags`
- `tags`: `id`, `platform_id`, `name`, `color`, `description`, `created_at`, `updated_at`.
- `player_tags`: `id`, `player_id`, `tag_id`, `created_at`. Índice: `UNIQUE (player_id, tag_id)`.

---

### 2.4. Módulo de Segmentação

#### `segments`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador do segmento |
| `platform_id` | `bigint` | `NOT NULL REFERENCES platforms(id) ON DELETE CASCADE` | Plataforma |
| `name` | `varchar(150)` | `NOT NULL` | Nome do segmento |
| `description` | `text` | `NULL` | Descrição do público-alvo |
| `rules_tree` | `jsonb` | `NOT NULL` | AST em JSON contendo grupos e regras aninhadas |
| `cached_count` | `integer` | `DEFAULT 0` | Contagem em cache de jogadores elegíveis |
| `last_calculated_at` | `timestamptz` | `NULL` | Timestamp do último cálculo de contagem |
| `created_at` | `timestamptz` | `NOT NULL` | Criação |
| `updated_at` | `timestamptz` | `NOT NULL` | Atualização |
| `deleted_at` | `timestamptz` | `NULL` | Soft delete |

#### `segment_groups` & `segment_conditions`
Tabelas auxiliares normalizadas para desdobramento de regras:
- `segment_groups`: `id`, `segment_id`, `parent_group_id`, `operator` (`AND`, `OR`).
- `segment_conditions`: `id`, `segment_group_id`, `field`, `operator` (`EQUALS`, `NOT_EQUALS`, `GREATER_THAN`, `LESS_THAN`, `CONTAINS`, `IN`, `NOT_IN`, `BETWEEN`), `value`.

---

### 2.5. Módulo de Templates e Provedores

#### `email_templates`
`id`, `platform_id`, `name`, `subject`, `body_html`, `body_json` (árvore do editor visual), `variables` (jsonb), `created_at`, `updated_at`, `deleted_at`.

#### `sms_templates`
`id`, `platform_id`, `name`, `content`, `characters_count`, `parts_count`, `variables` (jsonb), `created_at`, `updated_at`, `deleted_at`.

#### `providers` & `provider_credentials`
- `providers`: `id`, `slug` (`zenvia`, `brevo`, `infobip`, `sendgrid`, `fake_email`, `fake_sms`), `name`, `channel` (`EMAIL`, `SMS`, `WHATSAPP`), `status` (`ACTIVE`, `INACTIVE`), `rate_limit_per_second`, `rate_limit_per_minute`.
- `provider_credentials`: `id`, `platform_id`, `provider_id`, `encrypted_credentials` (text - AES-256 com `Laravel Crypt`), `is_default` (boolean).
- `provider_logs`: `id`, `provider_id`, `platform_id`, `endpoint`, `request_payload` (sanitizado), `response_payload`, `status_code`, `duration_ms`, `created_at`.

---

### 2.6. Módulo de Campanhas, Mensagens e Rastreamento

#### `campaigns`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador da campanha |
| `platform_id` | `bigint` | `NOT NULL REFERENCES platforms(id) ON DELETE RESTRICT` | Plataforma |
| `name` | `varchar(200)` | `NOT NULL` | Título da campanha |
| `description` | `text` | `NULL` | Descrição |
| `channel` | `varchar(30)` | `NOT NULL` | EMAIL, SMS, WHATSAPP, PUSH |
| `segment_id` | `bigint` | `NOT NULL REFERENCES segments(id)` | Segmento de público |
| `template_id` | `bigint` | `NOT NULL` | ID do template correspondente |
| `provider_id` | `bigint` | `NOT NULL REFERENCES providers(id)` | Provedor de envio |
| `sender` | `varchar(150)` | `NOT NULL` | Remetente (email ou sender ID do SMS) |
| `status` | `varchar(30)` | `NOT NULL DEFAULT 'DRAFT'` | DRAFT, SCHEDULED, RUNNING, PAUSED, COMPLETED, CANCELLED, FAILED |
| `scheduled_at` | `timestamptz` | `NULL` | Agendamento do disparo |
| `started_at` | `timestamptz` | `NULL` | Início efetivo do disparo |
| `completed_at` | `timestamptz` | `NULL` | Conclusão do envio |
| `total_recipients`| `integer` | `DEFAULT 0` | Contagem total de destinatários |
| `sent_count` | `integer` | `DEFAULT 0` | Mensagens enviadas |
| `delivered_count`| `integer` | `DEFAULT 0` | Mensagens entregues |
| `failed_count` | `integer` | `DEFAULT 0` | Falhas de envio |
| `opened_count` | `integer` | `DEFAULT 0` | Aberturas únicas |
| `clicked_count`| `integer` | `DEFAULT 0` | Cliques únicos |
| `created_by` | `bigint` | `NOT NULL REFERENCES users(id)` | Autor da campanha |
| `created_at` | `timestamptz` | `NOT NULL` | Criação |
| `updated_at` | `timestamptz` | `NOT NULL` | Atualização |
| `deleted_at` | `timestamptz` | `NULL` | Soft delete |

#### `campaign_recipients`
`id`, `campaign_id`, `player_id`, `status` (`PENDING`, `DISPATCHED`, `SKIPPED`, `FAILED`), `skip_reason` (`NO_CONSENT`, `INVALID_CONTACT`, `UNSUBSCRIBED`, `BLOCKED`), `created_at`.
Índice: `UNIQUE (campaign_id, player_id)`.

#### `messages`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | ID da mensagem |
| `platform_id` | `bigint` | `NOT NULL REFERENCES platforms(id)` | Plataforma |
| `campaign_id` | `bigint` | `NULL REFERENCES campaigns(id)` | Campanha vinculada (se houver) |
| `player_id` | `bigint` | `NOT NULL REFERENCES players(id)` | Jogador destinatário |
| `channel` | `varchar(30)` | `NOT NULL` | EMAIL, SMS, WHATSAPP |
| `provider` | `varchar(50)` | `NOT NULL` | Provedor que executou |
| `provider_message_id` | `varchar(255)` | `NULL` | Identificador retornado pelo provedor |
| `status` | `varchar(30)` | `NOT NULL DEFAULT 'QUEUED'` | QUEUED, SENT, DELIVERED, FAILED, BOUNCED |
| `recipient_address` | `varchar(255)` | `NOT NULL` | Email ou telefone destinatário |
| `subject` | `varchar(255)` | `NULL` | Assunto |
| `content` | `text` | `NOT NULL` | Corpo renderizado da mensagem |
| `queued_at` | `timestamptz` | `NOT NULL` | Timestamp de enfileiramento |
| `sent_at` | `timestamptz` | `NULL` | Timestamp de envio |
| `delivered_at`| `timestamptz` | `NULL` | Timestamp de entrega |
| `opened_at` | `timestamptz` | `NULL` | Primeira abertura |
| `clicked_at` | `timestamptz` | `NULL` | Primeiro clique |
| `failed_at` | `timestamptz` | `NULL` | Falha de envio |
| `error_message` | `text` | `NULL` | Detalhe do erro |
| `created_at` | `timestamptz` | `NOT NULL` | Criação |
| `updated_at` | `timestamptz` | `NOT NULL` | Atualização |
| **Índices** | `INDEX (provider, provider_message_id)` | | Rastreamento por webhook de provedor |
| | `INDEX (platform_id, player_id)` | | Histórico do jogador |
| | `INDEX (platform_id, campaign_id, status)` | | Métricas de campanha |

#### `message_events` (Tabela Particionada Mensalmente)
`id`, `message_id`, `event_type` (`QUEUED`, `SENT`, `DELIVERED`, `OPENED`, `CLICKED`, `BOUNCED`, `FAILED`, `UNSUBSCRIBED`), `payload` (jsonb), `ip_address`, `user_agent`, `created_at`.

---

### 2.7. Eventos, Webhooks e Idempotência

#### `event_types` & `events`
- `event_types`: `id`, `name` (`player.created`, `player.login`, `campaign.completed`, etc.), `description`.
- `events`: `id`, `platform_id`, `player_id`, `event_type_id`, `payload` (jsonb), `occurred_at`, `created_at`.

#### `webhooks` & `webhook_logs`
- `webhooks`: `id`, `platform_id`, `name`, `url`, `event_types` (jsonb), `secret`, `status` (`ACTIVE`, `INACTIVE`), `created_at`.
- `webhook_logs`: `id`, `platform_id`, `idempotency_key` (varchar(255) NULL), `source` (`PLAYER_WEBHOOK`, `PROVIDER_WEBHOOK`), `provider` (varchar(50) NULL), `payload` (jsonb), `status` (`RECEIVED`, `PROCESSED`, `DUPLICATE`, `FAILED`), `error_message`, `response_status`, `duration_ms`, `created_at`.
  - Índice: `UNIQUE (platform_id, idempotency_key)` para garantia física de não duplicação.

---

### 2.8. Motor de Automações

#### `automations`
`id`, `platform_id`, `name`, `trigger_type` (`player.created`, `player.inactive`, `tag.added`, etc.), `status` (`ACTIVE`, `PAUSED`, `DRAFT`), `settings` (jsonb), `created_at`, `updated_at`, `deleted_at`.

#### `automation_nodes`
`id`, `automation_id`, `type` (`TRIGGER`, `CONDITION`, `DELAY`, `EMAIL`, `SMS`, `ADD_TAG`, `REMOVE_TAG`, `UPDATE_FIELD`, `END`), `config` (jsonb), `position_x` (int), `position_y` (int).

#### `automation_edges`
`id`, `automation_id`, `source_node_id`, `target_node_id`, `condition_branch` (`yes`, `no`, `default`).

#### `automation_executions`
`id`, `automation_id`, `player_id`, `current_node_id`, `status` (`RUNNING`, `WAITING_DELAY`, `COMPLETED`, `FAILED`), `context_data` (jsonb), `scheduled_resume_at`, `started_at`, `finished_at`, `error_message`.

---

### 2.9. Governança LGPD, Consentimentos e Auditoria

#### `consents`
| Coluna | Tipo | Modificadores | Descrição |
| :--- | :--- | :--- | :--- |
| `id` | `bigserial` | `PRIMARY KEY` | Identificador do consentimento |
| `player_id` | `bigint` | `NOT NULL REFERENCES players(id) ON DELETE CASCADE` | Jogador |
| `channel` | `varchar(30)` | `NOT NULL` | EMAIL, SMS, WHATSAPP, PUSH |
| `is_granted` | `boolean` | `NOT NULL DEFAULT TRUE` | Status ativo ou revogado |
| `consent_date` | `timestamptz` | `NOT NULL` | Data/hora do aceite |
| `consent_source` | `varchar(100)` | `NOT NULL` | Origem (ex: registration_form, api, import) |
| `consent_ip` | `varchar(45)` | `NULL` | IP de origem do consentimento |
| `consent_version` | `varchar(50)` | `NULL` | Versão do termo aceito |
| `revoked_at` | `timestamptz` | `NULL` | Data de revogação/opt-out |
| `created_at` | `timestamptz` | `NOT NULL` | Criação |
| `updated_at` | `timestamptz` | `NOT NULL` | Atualização |
| **Índices** | `INDEX (player_id, channel, is_granted)` | | Validação atômica em CanSendMessageService |

#### `unsubscribe_requests`
`id`, `platform_id`, `player_id`, `channel`, `reason`, `ip_address`, `created_at`.

#### `audit_logs`
`id`, `platform_id`, `user_id` (NULL se for ação de sistema/API), `action` (login, logout, create, update, delete, send_campaign, rotate_key), `entity_type`, `entity_id`, `old_values` (jsonb com dados sensíveis mascarados), `new_values` (jsonb), `ip_address`, `user_agent`, `created_at`.

#### `api_keys`
`id`, `platform_id`, `name`, `key_prefix` (varchar(10)), `hashed_token` (varchar(255) NOT NULL UNIQUE), `scopes` (jsonb), `last_used_at`, `expires_at`, `created_at`, `updated_at`.
