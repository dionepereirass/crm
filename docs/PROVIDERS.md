# BET CRM — Camada de Provedores e Mensageria (Fase 7)

## 1. Visão Geral da Arquitetura

A **Fase 7** introduz no BET CRM uma infraestrutura profissional, escalável e desacoplada para envio de comunicações transacionais via **E-mail** e **SMS**. Esta camada serve como base operacional para os futuros motores de Campanhas (Fase 8) e Automações/Jornadas (Fase 9).

```
                      [ Client / Event / Campaign ]
                                   │
                                   ▼
                        [ MessageService ]
                (Redis Atomic Lock + DB Idempotency)
                                   │
                                   ▼
                           [ SendMessageJob ]
                      (Queue: 'messages', 3 Tries)
                                   │
            ┌──────────────────────┴──────────────────────┐
            ▼                                             ▼
   [ RateLimiterService ]                       [ CircuitBreakerService ]
 (Redis Sliding Window 60s)                    (CLOSED / OPEN / HALF_OPEN)
            │                                             │
            └──────────────────────┬──────────────────────┘
                                   ▼
                    [ MessageProviderResolver ]
              (Default, Prioridade #1, ou Fallback)
                                   │
       ┌───────────────────────────┼───────────────────────────┐
       ▼                           ▼                           ▼
[ BrevoEmailProvider ]   [ ZenviaSmsProvider ]      [ Fake Drivers (Dev) ]
  (Brevo SMTP API v3)      (Zenvia SMS API v2)        (Simulador Local)
       │                           │                           │
       └───────────────────────────┼───────────────────────────┘
                                   ▼
                       [ ProviderLog & MessageEvents ]
                        (Sanitizado - Zero Segredos)
```

---

## 2. Padrão de Projeto e Contratos

A comunicação com serviços externos é abstraída através de interfaces fortemente tipadas:

- `App\Services\Providers\Contracts\MessageProviderInterface`:
  - `send(MessagePayload $payload): ProviderResult`
  - `validateConfiguration(): ProviderHealthResult`
  - `getDriver(): string`
  - `getChannel(): string`
  - `getName(): string`
- `App\Services\Providers\Contracts\EmailProviderInterface`: Extende `MessageProviderInterface` para o canal EMAIL.
- `App\Services\Providers\Contracts\SmsProviderInterface`: Extende `MessageProviderInterface` para o canal SMS.

### Drivers Implementados

1. **`FakeEmailProvider` (`fake_email`)**:
   - Simulação local e segura de envios de e-mail sem tráfego de rede ou cobrança financeira.
   - Gera IDs simulados `fake_email_{uuid}` e registra logs de auditoria.
2. **`FakeSmsProvider` (`fake_sms`)**:
   - Simulação local de envios de SMS com cálculo de tamanho e suporte a homologação.
   - Gera IDs simulados `fake_sms_{uuid}`.
3. **`BrevoEmailProvider` (`brevo`)**:
   - Integração com a API HTTP REST da Brevo (Sendinblue v3) em `https://api.brevo.com/v3/smtp/email`.
   - Autenticação via header `api-key`.
   - Health check via endpoint `GET /v3/account`.
4. **`ZenviaSmsProvider` (`zenvia`)**:
   - Integração com a API v2 da Zenvia em `https://api.zenvia.com/v2/channels/sms/messages`.
   - Autenticação via header `X-API-TOKEN`.
   - Health check via `GET /v2/status`.

---

## 3. Segurança Zero-Exposure e Criptografia AES-256

Em conformidade estrita com as diretrizes do BET CRM:

1. **Isolamento de Credenciais**:
   - As credenciais de cada provedor (chaves de API, senhas e tokens de autenticação) são armazenadas separadamente na tabela `provider_credentials`.
   - Os valores são criptografados com o mecanismo de criptografia nativo do Laravel (`Crypt::encryptString`, utilizando cifras autenticadas AES-256-CBC ou AES-256-GCM).
2. **Ocultação Absoluta em Respostas e Logs**:
   - O model `Provider` oculta (`$hidden`) a relação `credentials`.
   - O `ProviderResource` expõe apenas a flag booleana `credentials_configured: true|false`.
   - O DTO `ProviderResult` e os logs de auditoria `ProviderLog` sanitizam automaticamente qualquer chave confidencial (`api_key`, `api-key`, `token`, `secret`, `password`, `authorization`), substituindo seus valores por `[REDACTED]`.

---

## 4. Idempotência Rigorosa e Prevenção de Disparo Duplicado

Para evitar que erros de rede ou múltiplos cliques resultem em envios duplicados aos clientes:

1. **Camada Redis (Lock Atômico)**:
   - `MessageService` adquire uma chave atômica `message_idemp:{platform_id}:{idempotency_key}` com `SET NX EX 86400`.
   - Se a chave já existir, a requisição é interceptada antes de qualquer inserção ou dispatch.
2. **Camada de Banco de Dados**:
   - A tabela `messages` possui uma restrição de unicidade composta:
     `UNIQUE(platform_id, idempotency_key)`
   - Caso haja tentativa concorrente, o PostgreSQL rejeita com erro de unicidade e o registro existente é retornado de forma consistente.

---

## 5. Resiliência: Circuit Breaker, Rate Limiter e Retries

### Circuit Breaker (`CircuitBreakerService`)
Implementa máquina de estados baseada em Redis para cada provedor:
- **`CLOSED`**: Operação normal. Todas as requisições passam.
- **`OPEN`**: Ativado após 5 falhas consecutivas dentro de 60 segundos. Rejeita novas chamadas imediatamente por 30 segundos, acionando o provedor fallback configurado.
- **`HALF_OPEN`**: Permite uma requisição de teste para avaliar se o serviço externo restabeleceu a conectividade.

### Rate Limiter (`RateLimiterService`)
- Janela deslizante de 1 minuto em Redis (`ZADD / ZREMRANGEBYSCORE`).
- Se o limite de requisições por minuto do provedor for excedido, a mensagem é reagendada com atraso exponencial, evitando bloqueios na API externa.

### SendMessageJob e Classificação de Falhas
- Processamento assíncrono na fila prioritária `messages`.
- **3 Tentativas** com backoff progressivo: `10s, 60s, 300s`.
- Classificação inteligente de erros:
  - **Erros Reintentáveis (`RETRYABLE`)**: Falhas 5xx (servidor temporariamente indisponível), Erros 429 (Rate Limited), e timeouts de conexão HTTP.
  - **Erros Não-Reintentáveis (`NON_RETRYABLE`)**: Erros 401/403 (chave inválida), Erros 400/422 (número malformado, sintaxe de e-mail inválida). O job falha imediatamente sem consumir tentativas adicionais.

---

## 6. Endpoints da API (v1)

### Provedores (`/api/v1/providers`)
- `GET /api/v1/providers`: Listagem com filtros por canal, status e busca.
- `POST /api/v1/providers`: Cadastro de provedor e credenciais criptografadas.
- `GET /api/v1/providers/{id}`: Detalhes, configuração e histórico de logs.
- `PUT /api/v1/providers/{id}`: Atualização de metadados ou rotação de credenciais.
- `DELETE /api/v1/providers/{id}`: Exclusão lógica (Soft Delete).
- `POST /api/v1/providers/{id}/activate`: Ativação do provedor.
- `POST /api/v1/providers/{id}/deactivate`: Desativação do provedor.
- `GET /api/v1/providers/{id}/health`: Execução síncrona de verificação de saúde e latência.
- `POST /api/v1/providers/{id}/test`: Disparo de teste para o provedor específico.

### Mensagens (`/api/v1/messages`)
- `GET /api/v1/messages`: Fila e histórico de mensagens da plataforma.
- `POST /api/v1/messages/test`: Disparo síncrono ou assíncrono de teste.
- `GET /api/v1/messages/{id}`: Detalhes da mensagem e linha do tempo de eventos de entrega.
- `POST /api/v1/messages/{id}/retry`: Reenfileiramento manual de mensagens que falharam.
- `POST /api/v1/messages/{id}/cancel`: Cancelamento de envio em fila (`QUEUED`).

### Webhooks de Provedores (`/api/v1/providers/webhooks/{driver}`)
- Ingestão de DLRs (Delivery Receipts) externos para atualização do status de entrega (`DELIVERED`, `BOUNCED`).
