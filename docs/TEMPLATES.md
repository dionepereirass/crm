# BET CRM — Sistema de Templates de Comunicação (Fase 6)

## 1. Visão Geral

O **Sistema de Templates de Comunicação** do BET CRM é a camada central de gerenciamento, composição, renderização e versionamento de mensagens para os canais **E-mail** e **SMS**. Ele foi projetado para alimentar futuramente o motor de campanhas e automações (Fases 7 e 8), garantindo:

1. **Isolamento Multi-Plataforma Estrito**: Templates e versões são vinculados exclusivamente a um `platform_id` via `BelongsToPlatform` e `TenantPlatformContext`.
2. **Imutabilidade e Rastreabilidade de Versões**: Uma versão publicada nunca é sobrescrita silenciosamente. Qualquer alteração em um template ativo cria automaticamente a versão `vN+1` em estado de rascunho.
3. **Catálogo Unificado de Variáveis Seguras**: Validação sintática rigorosa das tags `{{categoria.campo}}` e `{{categoria.campo|default:"valor"}}`, impedindo vazamento de dados confidenciais e uso indevido de variáveis em canais incompatíveis.
4. **Sanitização de HTML contra XSS**: Remoção rigorosa de tags perigosas (`<script>`, `<iframe>`, `<object>`, `<embed>`) e atributos maliciosos (`onload=`, `onclick=`, `javascript:`), preservando estilos inline, tabelas e tags semânticas necessárias para clientes de e-mail (Outlook, Gmail, Apple Mail).
5. **Cálculo Preciso de Métricas SMS**: Identificação de caracteres fora do padrão GSM 7-bit básico, detecção automática de codificação Unicode e contagem exata de segmentos/créditos (160/153 para GSM-7; 70/67 para Unicode).

---

## 2. Arquitetura de Banco de Dados

### Tabela `templates` (Mestre)
```sql
CREATE TABLE templates (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL UNIQUE,
    platform_id BIGINT NOT NULL REFERENCES platforms(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description TEXT NULL,
    channel VARCHAR(20) NOT NULL, -- 'EMAIL', 'SMS'
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', -- 'DRAFT', 'ACTIVE', 'ARCHIVED'
    category VARCHAR(50) NOT NULL DEFAULT 'GENERAL', -- 'WELCOME', 'DEPOSIT', 'WITHDRAWAL', 'REACTIVATION', 'PROMOTIONAL', 'VIP'
    current_version_id BIGINT NULL,
    created_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

### Tabela `template_versions` (Snapshot de Conteúdo e Variáveis)
```sql
CREATE TABLE template_versions (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL UNIQUE,
    template_id BIGINT NOT NULL REFERENCES templates(id) ON DELETE CASCADE,
    version INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', -- 'DRAFT', 'PUBLISHED', 'ARCHIVED'
    subject VARCHAR(255) NULL,
    preheader VARCHAR(255) NULL,
    html_content TEXT NULL,
    text_content TEXT NULL,
    sms_content TEXT NULL,
    variables_schema JSONB NULL,
    metadata JSONB NULL,
    changelog TEXT NULL,
    created_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT unique_template_version UNIQUE (template_id, version)
);
```

---

## 3. Catálogo Oficial de Variáveis Dinâmicas

As variáveis utilizam a sintaxe de chaves duplas: `{{chave}}` ou com fallback seguro: `{{chave|default:"Meu Fallback"}}`.

| Chave | Categoria | Tipo | Canais | Exemplo | Descrição |
|---|---|---|---|---|---|
| `player.name` | PLAYER | string | EMAIL, SMS | Carlos Santos | Nome completo do jogador |
| `player.first_name` | PLAYER | string | EMAIL, SMS | Carlos | Primeiro nome extraído |
| `player.email` | PLAYER | string | EMAIL | carlos@email.com | E-mail cadastrado (apenas e-mail) |
| `player.phone` | PLAYER | string | EMAIL, SMS | 5531998877661 | Telefone/WhatsApp normalizado |
| `player.city` | PLAYER | string | EMAIL, SMS | Belo Horizonte | Cidade de residência |
| `player.state` | PLAYER | string | EMAIL, SMS | MG | Sigla do Estado |
| `player.birth_date` | PLAYER | date | EMAIL | 12/04/1988 | Data de nascimento formatada |
| `player.created_at` | PLAYER | date | EMAIL, SMS | 01/01/2026 | Data de cadastro no CRM |
| `player.external_id` | ACCOUNT | string | EMAIL, SMS | PLY-1001 | ID da conta na casa de aposta |
| `player.status` | ACCOUNT | string | EMAIL | ACTIVE | Status da conta |
| `player.affiliate` | ACCOUNT | string | EMAIL, SMS | afiliado_top | Código de afiliação |
| `player.deposit.total` | FINANCIAL | currency | EMAIL, SMS | R$ 1.500,00 | Volume total de depósitos confirmados |
| `player.deposit.count` | FINANCIAL | number | EMAIL, SMS | 5 | Quantidade de depósitos |
| `player.deposit.last_amount` | FINANCIAL | currency | EMAIL, SMS | R$ 150,00 | Valor do último depósito |
| `player.bet.total` | FINANCIAL | currency | EMAIL, SMS | R$ 3.200,00 | Total acumulado de apostas |
| `player.bet.count` | FINANCIAL | number | EMAIL, SMS | 42 | Quantidade de apostas realizadas |
| `player.bet.last_amount` | FINANCIAL | currency | EMAIL, SMS | R$ 50,00 | Valor da última aposta |
| `segment.name` | SEGMENTATION | string | EMAIL, SMS | VIPs Ouro | Nome do segmento ativo |
| `platform.name` | PLATFORM | string | EMAIL, SMS | Bet Brasil | Nome comercial da plataforma |
| `platform.slug` | PLATFORM | string | EMAIL, SMS | bet-brasil | Identificador único da plataforma |

---

## 4. Regras do Mecanismo de SMS (GSM-7 vs Unicode)

1. **GSM 7-bit Padrão**:
   - Mensagem simples: até 160 caracteres = 1 crédito/segmento.
   - Mensagem multipart (concatenada): cada segmento utiliza 153 caracteres (7 caracteres consumidos pelo User Data Header UDH).
2. **Unicode (UCS-2)**:
   - Acionado quando a mensagem contém qualquer caractere fora do alfabeto GSM-7 (ex: acentos agudos como `á`, `ó`, cedilha `ç`, emojis).
   - Mensagem simples: até 70 caracteres = 1 crédito/segmento.
   - Mensagem multipart (concatenada): cada segmento utiliza 67 caracteres.

---

## 5. Endpoints da API REST

| Método | Endpoint | Permissão RBAC | Descrição |
|---|---|---|---|
| `GET` | `/api/v1/templates` | `templates.view` | Lista templates com paginação e filtros |
| `POST` | `/api/v1/templates` | `templates.create` | Cria novo template com versão inicial v1 |
| `GET` | `/api/v1/templates/{id}` | `templates.view` | Detalhes do template e versão ativa |
| `PUT` | `/api/v1/templates/{id}` | `templates.update` | Atualiza metadados ou cria vN+1 se publicado |
| `DELETE` | `/api/v1/templates/{id}` | `templates.delete` | Exclui (soft delete) template |
| `POST` | `/api/v1/templates/{id}/publish` | `templates.publish` | Publica o template e a versão atual |
| `POST` | `/api/v1/templates/{id}/archive` | `templates.archive` | Arquiva o template |
| `POST` | `/api/v1/templates/{id}/duplicate` | `templates.duplicate` | Duplica o template como rascunho |
| `POST` | `/api/v1/templates/{id}/preview` | `templates.preview` | Gera preview renderizado com contexto mock |
| `GET` | `/api/v1/templates/{id}/versions` | `templates.versions` | Lista histórico de versões |
| `GET` | `/api/v1/templates/{id}/versions/{v}` | `templates.versions` | Detalha versão histórica específica |
| `POST` | `/api/v1/templates/{id}/versions` | `templates.create` | Cria versão explícita para o template |
| `POST` | `/api/v1/templates/{id}/versions/{v}/publish` | `templates.publish` | Publica e torna versão histórica ativa |
| `POST` | `/api/v1/templates/{id}/versions/{v}/restore` | `templates.restore` | Restaura versão histórica como versão N+1 |
| `GET` | `/api/v1/templates/variables/catalog` | `templates.view` | Catálogo de variáveis dinâmicas |
| `GET` | `/api/v1/templates/categories/list` | `templates.view` | Lista de categorias oficiais |
