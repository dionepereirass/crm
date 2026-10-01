<?php

namespace App\Services\Providers\Drivers;

use App\DTOs\Providers\MessagePayload;
use App\DTOs\Providers\ProviderHealthResult;
use App\DTOs\Providers\ProviderResult;
use App\Services\Providers\Contracts\SmsProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZenviaSmsProvider implements SmsProviderInterface
{
    protected string $apiToken;
    protected string $baseUrl;
    protected int $timeout;
    protected string $from;

    public function __construct(
        protected array $credentials = [],
        protected array $configuration = []
    ) {
        $this->apiToken = $credentials['api_token'] ?? env('ZENVIA_API_TOKEN', '');
        $this->baseUrl = rtrim($configuration['base_url'] ?? env('ZENVIA_BASE_URL', 'https://api.zenvia.com/v2'), '/');
        $this->timeout = (int) ($configuration['timeout'] ?? env('ZENVIA_TIMEOUT', 10));
        $this->from = $configuration['from'] ?? 'BETCRM';
    }

    public function send(MessagePayload $message): ProviderResult
    {
        if (empty($this->apiToken)) {
            return ProviderResult::failure(
                errorMessage: 'Token de API da Zenvia não configurado.',
                errorCode: 'INVALID_CREDENTIALS',
                provider: $this->getDriver()
            );
        }

        $url = "{$this->baseUrl}/channels/sms/messages";

        // Higieniza número de telefone (apenas dígitos)
        $cleanPhone = preg_replace('/\D/', '', $message->recipient);

        $payload = [
            'from' => $this->from,
            'to' => $cleanPhone,
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $message->smsContent ?? $message->text ?? '',
                ],
            ],
        ];

        $startTime = hrtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-TOKEN' => $this->apiToken,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ])
                ->post($url, $payload);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $messageId = $data['id'] ?? 'zenvia_' . uniqid();

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
            $errorMessage = $body['message'] ?? $body['error'] ?? "Falha na API Zenvia HTTP {$statusCode}";

            $errorCode = match ($statusCode) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                429 => 'RATE_LIMITED',
                default => ($statusCode >= 500 ? 'SERVER_ERROR' : 'HTTP_ERROR'),
            };

            Log::warning("[ZENVIA ERROR] Falha no envio de SMS", [
                'status_code' => $statusCode,
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'recipient' => $this->maskPhone($cleanPhone),
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
                errorMessage: 'Timeout ou erro de conexão com a API Zenvia: ' . $e->getMessage(),
                errorCode: 'CONNECTION_TIMEOUT',
                provider: $this->getDriver()
            );
        } catch (Throwable $e) {
            return ProviderResult::failure(
                errorMessage: 'Exceção inesperada na integração Zenvia: ' . $e->getMessage(),
                errorCode: 'UNEXPECTED_ERROR',
                provider: $this->getDriver()
            );
        }
    }

    public function validateConfiguration(): ProviderHealthResult
    {
        if (empty($this->apiToken)) {
            return new ProviderHealthResult(
                configured: false,
                reachable: false,
                authenticated: false,
                latencyMs: null,
                error: 'Token de API da Zenvia não configurado.',
                driver: $this->getDriver()
            );
        }

        $url = "{$this->baseUrl}/status";
        $startTime = hrtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-TOKEN' => $this->apiToken,
                    'accept' => 'application/json',
                ])
                ->get($url);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            // Zenvia status endpoint can return 200 or 404 if status route is different, but response indicates reachability
            if ($response->status() !== 401 && $response->status() !== 403) {
                return new ProviderHealthResult(
                    configured: true,
                    reachable: true,
                    authenticated: true,
                    latencyMs: $latencyMs,
                    error: null,
                    driver: $this->getDriver()
                );
            }

            return new ProviderHealthResult(
                configured: true,
                reachable: true,
                authenticated: false,
                latencyMs: $latencyMs,
                error: "Falha de autenticação Zenvia (HTTP {$response->status()}).",
                driver: $this->getDriver()
            );
        } catch (Throwable $e) {
            return new ProviderHealthResult(
                configured: true,
                reachable: false,
                authenticated: false,
                latencyMs: null,
                error: 'Endpoint Zenvia inalcançável: ' . $e->getMessage(),
                driver: $this->getDriver()
            );
        }
    }

    public function getName(): string
    {
        return 'Zenvia SMS API';
    }

    public function getChannel(): string
    {
        return 'SMS';
    }

    public function getDriver(): string
    {
        return 'zenvia';
    }

    protected function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len >= 8) {
            return substr($phone, 0, 4) . '****' . substr($phone, -4);
        }
        return '****';
    }
}
