<?php

namespace App\Services\Providers\Drivers;

use App\DTOs\Providers\MessagePayload;
use App\DTOs\Providers\ProviderHealthResult;
use App\DTOs\Providers\ProviderResult;
use App\Services\Providers\Contracts\EmailProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BrevoEmailProvider implements EmailProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;
    protected string $senderEmail;
    protected string $senderName;

    public function __construct(
        protected array $credentials = [],
        protected array $configuration = []
    ) {
        $this->apiKey = $credentials['api_key'] ?? env('BREVO_API_KEY', '');
        $this->baseUrl = rtrim($configuration['base_url'] ?? env('BREVO_BASE_URL', 'https://api.brevo.com/v3'), '/');
        $this->timeout = (int) ($configuration['timeout'] ?? env('BREVO_TIMEOUT', 10));
        $this->senderEmail = $configuration['sender_email'] ?? 'comunicacao@betcrm.com';
        $this->senderName = $configuration['sender_name'] ?? 'BET CRM';
    }

    public function send(MessagePayload $message): ProviderResult
    {
        if (empty($this->apiKey)) {
            return ProviderResult::failure(
                errorMessage: 'Chave de API do Brevo não configurada.',
                errorCode: 'INVALID_CREDENTIALS',
                provider: $this->getDriver()
            );
        }

        $url = "{$this->baseUrl}/smtp/email";

        $payload = [
            'sender' => [
                'name' => $this->senderName,
                'email' => $this->senderEmail,
            ],
            'to' => [
                [
                    'email' => $message->recipient,
                    'name' => $message->recipientName ?: $message->recipient,
                ],
            ],
            'subject' => $message->subject ?: 'Comunicação Importante',
        ];

        if (!empty($message->html)) {
            $payload['htmlContent'] = $message->html;
        }

        if (!empty($message->text)) {
            $payload['textContent'] = $message->text;
        }

        if ($message->idempotencyKey) {
            $payload['tags'] = ['crm', 'platform_' . ($message->platformId ?? 'general')];
        }

        $startTime = hrtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ])
                ->post($url, $payload);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $messageId = $data['messageId'] ?? 'brevo_' . uniqid();

                return ProviderResult::success(
                    providerMessageId: (string) $messageId,
                    provider: $this->getDriver(),
                    rawResponse: [
                        'status_code' => $response->status(),
                        'message_id' => $messageId,
                        'latency_ms' => $latencyMs,
                    ],
                    metadata: [
                        'latency_ms' => $latencyMs,
                    ]
                );
            }

            $statusCode = $response->status();
            $body = $response->json() ?? [];
            $errorMessage = $body['message'] ?? "Falha na API Brevo HTTP {$statusCode}";

            $errorCode = match ($statusCode) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                429 => 'RATE_LIMITED',
                default => ($statusCode >= 500 ? 'SERVER_ERROR' : 'HTTP_ERROR'),
            };

            Log::warning("[BREVO ERROR] Falha no envio de e-mail", [
                'status_code' => $statusCode,
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'recipient' => $this->maskRecipient($message->recipient),
            ]);

            return ProviderResult::failure(
                errorMessage: $errorMessage,
                errorCode: $errorCode,
                provider: $this->getDriver(),
                rawResponse: [
                    'status_code' => $statusCode,
                    'error_message' => $errorMessage,
                    'latency_ms' => $latencyMs,
                ]
            );
        } catch (ConnectionException $e) {
            return ProviderResult::failure(
                errorMessage: 'Timeout ou erro de conexão com a API Brevo: ' . $e->getMessage(),
                errorCode: 'CONNECTION_TIMEOUT',
                provider: $this->getDriver()
            );
        } catch (Throwable $e) {
            return ProviderResult::failure(
                errorMessage: 'Exceção inesperada na integração Brevo: ' . $e->getMessage(),
                errorCode: 'UNEXPECTED_ERROR',
                provider: $this->getDriver()
            );
        }
    }

    public function validateConfiguration(): ProviderHealthResult
    {
        if (empty($this->apiKey)) {
            return new ProviderHealthResult(
                configured: false,
                reachable: false,
                authenticated: false,
                latencyMs: null,
                error: 'Chave de API do Brevo não configurada.',
                driver: $this->getDriver()
            );
        }

        $url = "{$this->baseUrl}/account";
        $startTime = hrtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                ])
                ->get($url);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            if ($response->successful()) {
                return new ProviderHealthResult(
                    configured: true,
                    reachable: true,
                    authenticated: true,
                    latencyMs: $latencyMs,
                    error: null,
                    driver: $this->getDriver()
                );
            }

            $statusCode = $response->status();
            $body = $response->json() ?? [];
            $errMsg = $body['message'] ?? "Falha de autenticação Brevo (HTTP {$statusCode}).";

            return new ProviderHealthResult(
                configured: true,
                reachable: true,
                authenticated: false,
                latencyMs: $latencyMs,
                error: $errMsg,
                driver: $this->getDriver()
            );
        } catch (Throwable $e) {
            return new ProviderHealthResult(
                configured: true,
                reachable: false,
                authenticated: false,
                latencyMs: null,
                error: 'Endpoint Brevo inalcançável: ' . $e->getMessage(),
                driver: $this->getDriver()
            );
        }
    }

    public function getName(): string
    {
        return 'Brevo E-mail API';
    }

    public function getChannel(): string
    {
        return 'EMAIL';
    }

    public function getDriver(): string
    {
        return 'brevo';
    }

    protected function maskRecipient(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';
        return mb_substr($name, 0, 2) . '***@' . $domain;
    }
}
