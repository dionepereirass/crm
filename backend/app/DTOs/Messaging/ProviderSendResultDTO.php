<?php

namespace App\DTOs\Messaging;

readonly class ProviderSendResultDTO
{
    public function __construct(
        public bool $success,
        public string $providerMessageId,
        public string $provider,
        public string $status, // 'SENT', 'QUEUED', 'FAILED'
        public ?string $errorMessage = null,
        public array $rawResponse = []
    ) {}
}
