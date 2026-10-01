<?php

namespace App\DTOs\Messaging;

readonly class HealthCheckResultDTO
{
    public function __construct(
        public string $provider,
        public bool $healthy,
        public int $latencyMs,
        public ?string $message = null,
        public array $details = []
    ) {}
}
