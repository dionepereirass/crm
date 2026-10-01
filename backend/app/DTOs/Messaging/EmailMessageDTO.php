<?php

namespace App\DTOs\Messaging;

readonly class EmailMessageDTO
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $htmlContent,
        public ?string $senderEmail = null,
        public ?string $senderName = null,
        public ?string $replyTo = null,
        public array $metadata = []
    ) {}
}
