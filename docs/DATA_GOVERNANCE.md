# BET CRM — GOVERNANÇA DE DADOS E CICLO DE RETENÇÃO

## 1. Princípios de Governança de Dados

A Governança de Dados no **BET CRM** estabelece regras estritas para a conservação e o descarte sistemático de informações, prevenindo a retenção indefinida de dados sensíveis e garantindo o princípio da necessidade (Art. 6º, III da LGPD).

---

## 2. Categorias de Retenção e Políticas Padrão

| Categoria (`data_category`) | Descrição | Prazo Padrão | Ações Permitidas |
|---|---|:---:|:---:|
| `WEBHOOK_LOGS` | Payloads brutos recebidos das plataformas de apostas | 30 dias | `DELETE`, `RETAIN` |
| `MESSAGE_EVENTS` | Registros analíticos e de tracking de e-mails/SMS | 90 dias | `DELETE`, `ANONYMIZE`, `RETAIN` |
| `EXPORT_FILES` | Arquivos compactados de portabilidade e relatórios | 7 dias | `DELETE`, `RETAIN` |
| `TEMPORARY_TOKENS` | Tokens de login temporário e sessões expiradas | 1 dia | `DELETE`, `RETAIN` |
| `INACTIVE_PLAYERS` | Apostadores sem movimentação nem login por longo período | 1825 dias (5 anos) | `ANONYMIZE`, `DELETE`, `RETAIN` |
| `AUDIT_LOGS` | Trilha de auditoria administrativa | 730 dias (2 anos) | `RETAIN`, `DELETE` |

---

## 3. Processamento em Lotes e Alta Performance (`RetentionService`)

O expurgo e anonimização não realizam bloqueios de tabela e operam através de processamento em chunks:

```php
// Expurgo de logs de webhooks em lotes de 500 registros
WebhookLog::where('platform_id', $platformId)
    ->where('received_at', '<', $cutoffDate)
    ->chunkById(500, function ($logs) {
        // deleção segura
    });
```

### Agendamento Automatizado:
A classe `ProcessRetentionPoliciesJob` é executada periodicamente via cron/scheduler no worker Laravel Horizon (`queue: default`), iterando sobre as plataformas ativas e aplicando as regras parametrizadas em `retention_policies`.

---

## 4. Trilha Geral de Auditoria (`audit_logs`)

Todas as operações de governança, criação de regras, anonimizações e exportações são catalogadas com os seguintes metadados:

- `platform_id`: Isolamento estrito por operadora.
- `actor_type` & `actor_id`: Identificação inequívoca do usuário ou processo que disparou a ação.
- `action`: Verbo operacional (`CREATE`, `UPDATE`, `DELETE`, `ANONYMIZE`, `EXPORT`, `GRANT_CONSENT`, `REVOKE_CONSENT`).
- `resource_type` & `resource_id`: Entidade alvo (ex: `Player #1024`, `Consent #42`).
- `old_values` & `new_values`: Snapshot dos atributos modificados, devidamente higienizados pelo `SensitiveDataSanitizer` (senhas, chaves de API e tokens são ofuscados).
- `ip_address` & `user_agent`: Rastreabilidade de rede.
- `request_id`: Correlação com o ciclo de vida da requisição HTTP.

---

## 5. Auditoria de Acesso a Dados Pessoais (`PersonalDataAccessService`)

Para cumprir com as diretrizes da ANPD sobre rastreabilidade de acessos a dados pessoais protegidos:
- O serviço registra **quais campos** foram visualizados por determinado operador (ex: `['name', 'email', 'cpf']`).
- **NUNCA** armazena o valor bruto desses campos dentro do log de auditoria, garantindo que o próprio banco de logs não se torne um vetor de vazamento de credenciais ou dados sensíveis.
