# BET CRM — Guia de Integração de Webhooks

Este documento detalha o protocolo técnico oficial para envio de eventos por casas de apostas (Sportsbook e Cassino) para o **BET CRM**.

---

## 1. Visão Geral

O BET CRM disponibiliza um endpoint seguro de alta performance para recepção e ingestão assíncrona de eventos transacionais e cadastrais dos apostadores.

- **Método**: `POST`
- **URL**: `https://seu-dominio-crm.com/api/v1/webhooks/{platform_slug}`
- **Exemplo de URL Local**: `http://localhost:8000/api/v1/webhooks/bet-brasil`
- **Content-Type**: `application/json`
- **Tempo de Resposta Médio**: `< 20ms` (HTTP 202 Accepted com processamento enfileirado)

---

## 2. Autenticação & Assinatura HMAC-SHA256

Para garantir integridade, autenticidade e proteção contra adulterações, todas as requisições enviadas ao endpoint de webhook **DEVEM** incluir o cabeçalho `X-Webhook-Signature`.

### 2.1. Como Calcular a Assinatura

A assinatura é calculada através do algoritmo **HMAC-SHA256** utilizando a chave secreta da plataforma (`webhook_secret` fornecido no painel do BET CRM) e o **RAW BODY** exato da requisição HTTP (em formato string/bytes, antes de qualquer decodificação JSON).

$$\text{signature} = \text{hash\_hmac}('sha256', \text{raw\_body}, \text{webhook\_secret})$$

### 2.2. Cabeçalhos HTTP Exigidos

| Cabeçalho | Valor / Formato | Descrição |
| :--- | :--- | :--- |
| `Content-Type` | `application/json` | Tipo de mídia da requisição |
| `X-Webhook-Signature` | `<hash_hexadecimal>` ou `sha256=<hash_hexadecimal>` | Assinatura HMAC-SHA256 computada |

> [!CAUTION]
> **Atenção**: Nunca envie o `webhook_secret` no corpo ou nos cabeçalhos da requisição. A chave secreta deve permanecer confidencial apenas no seu backend.

---

## 3. Idempotência e Desduplicação

Para evitar duplicidades provocadas por oscilações de rede ou retentativas automáticas da casa de aposta:

1. Cada evento **DEVE** conter um identificador único no campo `event_id` (ou `id`, `external_id`, `transaction_id`).
2. O BET CRM garante consistência de chave única composta: `platform_id + external_event_id`.
3. Caso o mesmo `event_id` seja retransmitido para a mesma operadora:
   - Nenhum novo evento será criado;
   - Nenhum saldo ou contador será duplicado;
   - A API responderá imediatamente com status `HTTP 202 Accepted` indicando `"status": "DUPLICATE"`.

---

## 4. Eventos Suportados

| Evento Externo | Evento Interno | Descrição | Efeito no Jogador |
| :--- | :--- | :--- | :--- |
| `player.created` | `PLAYER_CREATED` | Cadastro de nova conta no operador | Criação do jogador no CRM |
| `player.updated` | `PLAYER_UPDATED` | Alteração cadastral (nome, cidade, UF, etc.) | Atualização segura dos campos |
| `deposit.success` | `DEPOSIT_SUCCESS` | Depósito financeiro liquidado | Atualiza `last_activity_at` |
| `bet.placed` | `BET_PLACED` | Aposta esportiva ou rodada de cassino submetida | Atualiza `last_activity_at` |
| `bet.settled` | `BET_SETTLED` | Aposta finalizada (ganha, perdida, devolvida) | Registrado na timeline 360° |
| `withdrawal.success` | `WITHDRAWAL_SUCCESS` | Saque aprovado e transferido | Registrado na timeline 360° |
| `login` | `LOGIN` | Sessão autenticada pelo apostador | Atualiza `last_login_at` |

---

## 5. Estrutura Padrão do Payload

```json
{
  "event_id": "evt_984729104",
  "event": "deposit.success",
  "timestamp": "2026-10-01T12:30:00Z",
  "player": {
    "external_id": "usr_99182",
    "name": "Carlos Silva",
    "email": "carlos@exemplo.com.br",
    "phone": "(11) 98765-4321"
  },
  "data": {
    "amount": 100.00,
    "currency": "BRL",
    "payment_method": "PIX",
    "transaction_id": "pix_tx_827103"
  }
}
```

> [!NOTE]
> Valores monetários são convertidos com precisão decimal (`DECIMAL(15,2)`), prevenindo erros de arredondamento causados por ponto flutuante.

---

## 6. Códigos de Resposta HTTP

| Código HTTP | Significado | Exemplo de Retorno |
| :--- | :--- | :--- |
| `202 Accepted` | Webhook aceito e colocado em fila | `{"success": true, "status": "ACCEPTED", "external_event_id": "evt_984729104"}` |
| `202 Accepted` | Evento duplicado reconhecido | `{"success": true, "status": "DUPLICATE", "external_event_id": "evt_984729104"}` |
| `401 Unauthorized` | Assinatura HMAC inválida | `{"success": false, "message": "Assinatura HMAC-SHA256 inválida."}` |
| `404 Not Found` | Plataforma inexistente ou inativa | `{"success": false, "message": "Plataforma não encontrada."}` |
| `422 Unprocessable` | JSON quebrado ou sem `event_id` | `{"success": false, "message": "Payload JSON inválido ou vazio."}` |
| `429 Too Many Req.` | Rate limit excedido (>300 req/min) | `{"success": false, "message": "Limite de requisições excedido..."}` |

---

## 7. Exemplos de Implementação

### 7.1. cURL (Bash / Terminal)

```bash
# 1. Definir variáveis
SECRET="whsec_seu_secret_aqui"
URL="http://localhost:8000/api/v1/webhooks/bet-brasil"
PAYLOAD='{"event_id":"evt_101","event":"deposit.success","timestamp":"2026-10-01T12:00:00Z","player":{"external_id":"usr_1"},"data":{"amount":100.00}}'

# 2. Gerar assinatura HMAC-SHA256
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

# 3. Disparar requisição
curl -X POST "$URL" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

### 7.2. PHP 8+

```php
<?php

$platformSlug = 'bet-brasil';
$webhookSecret = 'whsec_seu_secret_aqui';
$url = "http://localhost:8000/api/v1/webhooks/{$platformSlug}";

$payloadData = [
    'event_id' => 'evt_' . bin2hex(random_bytes(6)),
    'event' => 'deposit.success',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'player' => [
        'external_id' => '12345',
    ],
    'data' => [
        'amount' => 150.00,
        'currency' => 'BRL',
    ],
];

// O cálculo DEVE ser sobre a string crua enviada no body
$rawBody = json_encode($payloadData);
$signature = hash_hmac('sha256', $rawBody, $webhookSecret);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Webhook-Signature: ' . $signature,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\nResponse: {$response}\n";
```

### 7.3. Node.js / JavaScript (TypeScript)

```typescript
import crypto from "crypto";

const platformSlug = "bet-brasil";
const webhookSecret = "whsec_seu_secret_aqui";
const endpoint = `http://localhost:8000/api/v1/webhooks/${platformSlug}`;

const payload = {
  event_id: `evt_${Date.now()}`,
  event: "bet.placed",
  timestamp: new Date().toISOString(),
  player: {
    external_id: "usr_99182",
  },
  data: {
    bet_amount: 50.0,
    odds: 2.10,
    sport: "Futebol",
  },
};

const rawBody = JSON.stringify(payload);

// Gera assinatura HMAC-SHA256
const signature = crypto
  .createHmac("sha256", webhookSecret)
  .update(rawBody)
  .digest("hex");

async function dispatchWebhook() {
  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Webhook-Signature": signature,
    },
    body: rawBody,
  });

  const data = await response.json();
  console.log(`Status: ${response.status}`, data);
}

dispatchWebhook();
```
