<?php

namespace App\DTOs\Messaging;

readonly class SmsMessageDTO
{
    public function __construct(
        public string $to,
        public string $message,
        public ?string $senderId = null,
        public array $metadata = []
    ) {}
}
