<?php

namespace App\DTOs\Providers;

class ProviderResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public string $status = 'SENT',
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public string $provider = 'unknown',
        public array $rawResponse = [],
        public array $metadata = []
    ) {}

    public static function success(
        string $providerMessageId,
        string $provider,
        array $rawResponse = [],
        array $metadata = []
    ): self {
        return new self(
            success: true,
            providerMessageId: $providerMessageId,
            status: 'SENT',
            provider: $provider,
            rawResponse: self::sanitizeResponse($rawResponse),
            metadata: $metadata
        );
    }

    public static function failure(
        string $errorMessage,
        ?string $errorCode = null,
        string $provider = 'unknown',
        array $rawResponse = [],
        array $metadata = []
    ): self {
        return new self(
            success: false,
            status: 'FAILED',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            provider: $provider,
            rawResponse: self::sanitizeResponse($rawResponse),
            metadata: $metadata
        );
    }

    /**
     * Remove quaisquer chaves sensíveis que possam vir por engano na resposta do provedor.
     */
    protected static function sanitizeResponse(array $response): array
    {
        $sensitiveKeys = ['api_key', 'token', 'secret', 'password', 'authorization', 'x-api-token', 'key'];

        $sanitized = [];
        foreach ($response as $k => $v) {
            $lowerKey = strtolower((string) $k);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $sanitized[$k] = self::sanitizeResponse($v);
            } else {
                $sanitized[$k] = $v;
            }
        }

        return $sanitized;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider_message_id' => $this->providerMessageId,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'provider' => $this->provider,
            'raw_response' => $this->rawResponse,
            'metadata' => $this->metadata,
        ];
    }
}
