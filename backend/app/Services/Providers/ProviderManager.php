<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\MessageProviderInterface;
use InvalidArgumentException;

class ProviderManager
{
    protected array $drivers = [];

    public function __construct()
    {
        // Register safe mock drivers
        $this->drivers['fake_email'] = new FakeEmailProvider();
        $this->drivers['fake_sms'] = new FakeSmsProvider();
    }

    /**
     * Resolve provider by slug or default for channel.
     */
    public function resolve(string $channel, ?string $providerSlug = null): MessageProviderInterface
    {
        $isMock = config('services.providers.mock_enabled', true) || app()->environment(['local', 'testing']);

        if ($isMock) {
            return match (strtoupper($channel)) {
                'EMAIL' => $this->drivers['fake_email'],
                'SMS' => $this->drivers['fake_sms'],
                default => throw new InvalidArgumentException("Unsupported channel: {$channel}"),
            };
        }

        // Production resolution (drivers to be implemented in Phase 7)
        if ($providerSlug && isset($this->drivers[$providerSlug])) {
            return $this->drivers[$providerSlug];
        }

        throw new InvalidArgumentException("Real provider [{$providerSlug}] not configured or active. Dev Mock must be used.");
    }

    /**
     * Get health status of all registered drivers.
     */
    public function checkAllHealth(): array
    {
        $results = [];
        foreach ($this->drivers as $slug => $driver) {
            $results[$slug] = $driver->healthCheck();
        }
        return $results;
    }
}
