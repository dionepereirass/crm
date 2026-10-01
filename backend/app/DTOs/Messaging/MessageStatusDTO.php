<?php

namespace App\DTOs\Messaging;

readonly class MessageStatusDTO
{
    public function __construct(
        public string $providerMessageId,
        public string $status, // 'QUEUED', 'SENT', 'DELIVERED', 'FAILED', 'BOUNCED'
        public ?string $deliveredAt = null,
        public ?string $errorMessage = null,
        public array $rawDetails = []
    ) {}
}
