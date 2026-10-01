# BET CRM — POLÍTICA DE PRIVACIDADE E PROTEÇÃO DE DADOS

## 1. Visão Geral

A camada de Privacidade do **BET CRM** foi concebida sob os princípios do *Privacy by Design* e *Privacy by Default*, garantindo conformidade irrestrita com a Lei Geral de Proteção de Dados Pessoais (**LGPD — Lei nº 13.709/2018**), com foco específico na vertical de apostas esportivas e iGaming.

O sistema assegura governança sobre todo o ciclo de vida dos dados: coleta, uso, compartilhamento, armazenamento, retenção e descarte irreversível.

---

## 2. Classificação de Dados Pessoais

O catálogo central é mantido pelo `PersonalDataRegistry`, agrupando os atributos dos apostadores em 6 categorias analíticas:

| Categoria | Descrição | Atributos Mapeados | Nível de Sensibilidade |
|---|---|---|---|
| **IDENTIFICATION** | Dados cadastrais do titular | `name`, `cpf`, `birth_date`, `external_id` | **ALTO** (Requer mascaramento / acesso restrito) |
| **CONTACT** | Canais de comunicação e contato | `email`, `phone`, `masked_email`, `masked_phone` | **MÉDIO / ALTO** (Sujeito a consentimento) |
| **LOCATION** | Dados de localização física/regional | `city`, `state`, `country`, `postal_code` | **MÉDIO** |
| **FINANCIAL** | Métricas financeiras e de transação | `lifetime_deposit`, `lifetime_withdrawal`, `balance`, `net_revenue` | **CONFIDENCIAL** (Art. 16, I - Preservação obrigatória) |
| **BEHAVIORAL** | Engajamento, apostas e risco | `risk_score`, `churn_probability`, `tags`, `favorite_games` | **INTERNO** |
| **TECHNICAL** | Conexão, dispositivos e segurança | `last_login_ip`, `user_agent`, `device_fingerprint` | **OPERACIONAL / AUDITÁVEL** |

---

## 3. Sanitização e Mascaramento de Dados

A higienização de informações é centralizada no `SensitiveDataSanitizer`:

1. **E-mail**: Formato parcial `ca***@email.com` (exibe os 2 primeiros caracteres do usuário e o domínio).
2. **Telefone**: Formato mascarado `55319****7661` (preserva código do país e DDD, oculta miolo numérico).
3. **CPF**: Formato `123.***.***-01` (preserva os primeiros 3 dígitos e os 2 dígitos verificadores).
4. **Cartão de Crédito**: Formato `**** **** **** 1234` (apenas os 4 últimos dígitos).
5. **Segredos e Credenciais**: Chaves com sufixos ou substrings como `password`, `secret`, `token`, `key`, `signature`, `bearer`, `auth`, `cert` são permanentemente substituídas por `[REDACTED]` antes de qualquer gravação em logs ou respostas públicas.

---

## 4. Gestão Central de Consentimento (Opt-In / Opt-Out)

O `ConsentPolicyService` atua como autoridade central de verificação antes de qualquer disparo ou ação:

- **8 Tipos de Consentimento**:
  - `MARKETING_EMAIL`
  - `MARKETING_SMS`
  - `MARKETING_WHATSAPP`
  - `MARKETING_PUSH`
  - `TERMS_OF_SERVICE`
  - `PRIVACY_POLICY`
  - `DATA_SHARING`
  - `ANALYTICS_TRACKING`
- **Evidência Criptográfica (`ConsentEvidenceService`)**:
  - Armazena payload canônico contendo timestamp, IP, User-Agent, origem e versão da política.
  - Gera hash SHA-256 da evidência (`evidence_hash`) para conferência e não-repúdio.
- **Tabela Histórica Imutável (`consent_history`)**:
  - Tabela append-only.
  - O modelo `ConsentHistory` rejeita alterações (`updating`) e exclusões (`deleting`) disparando `RuntimeException`.
- **Cache de Alta Performance com Invalidação Atômica**:
  - Consultas cacheadas em Redis com TTL de 60s sob a chave `betcrm:consent:{platform_id}:{player_id}:{type}`.
  - Eventos de modelo `saved` e `deleted` no modelo `Consent` invalidam imediatamente todos os canais associados.

---

## 5. Matriz de Autorização RBAC para Privacidade

| Permissão | SUPER_ADMIN | ADMIN | MARKETING | SUPPORT | ANALYST |
|---|:---:|:---:|:---:|:---:|:---:|
| `privacy.view` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `privacy.requests.manage` | ✓ | ✓ | — | — | — |
| `privacy.requests.assign` | ✓ | ✓ | — | — | — |
| `privacy.requests.process` | ✓ | ✓ | — | — | — |
| `privacy.requests.complete` | ✓ | ✓ | — | — | — |
| `privacy.requests.reject` | ✓ | ✓ | — | — | — |
| `privacy.data.export` | ✓ | ✓ | — | — | — |
| `privacy.data.anonymize` | ✓ | ✓ | — | — | — |
| `privacy.retention.view` | ✓ | ✓ | — | — | — |
| `privacy.retention.manage` | ✓ | ✓ | — | — | — |
| `privacy.retention.execute`| ✓ | ✓ | — | — | — |
| `privacy.audit.view` | ✓ | ✓ | — | — | ✓ |
| `privacy.consents.manage` | ✓ | ✓ | — | — | — |
