<?php

namespace App\DTOs\Providers;

class ProviderHealthResult
{
    public function __construct(
        public bool $configured,
        public bool $reachable,
        public bool $authenticated,
        public ?int $latencyMs = null,
        public ?string $error = null,
        public string $driver = '',
        public string $timestamp = ''
    ) {
        if (empty($this->timestamp)) {
            $this->timestamp = now()->toIso8601String();
        }
    }

    public function isHealthy(): bool
    {
        return $this->configured && $this->reachable && $this->authenticated && empty($this->error);
    }

    public function toArray(): array
    {
        return [
            'healthy' => $this->isHealthy(),
            'configured' => $this->configured,
            'reachable' => $this->reachable,
            'authenticated' => $this->authenticated,
            'latency_ms' => $this->latencyMs,
            'error' => $this->error,
            'driver' => $this->driver,
            'timestamp' => $this->timestamp,
        ];
    }
}
