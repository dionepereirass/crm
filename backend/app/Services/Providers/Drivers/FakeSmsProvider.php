<?php

namespace App\Services\Providers\Drivers;

use App\DTOs\Providers\MessagePayload;
use App\DTOs\Providers\ProviderHealthResult;
use App\DTOs\Providers\ProviderResult;
use App\Services\Providers\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeSmsProvider implements SmsProviderInterface
{
    public function __construct(
        protected array $credentials = [],
        protected array $configuration = []
    ) {}

    public function send(MessagePayload $message): ProviderResult
    {
        $mockId = 'fake_sms_' . Str::uuid()->toString();

        Log::info('[MOCK SMS DISPATCH] Simulating SMS delivery', [
            'to' => $message->recipient,
            'content' => $message->smsContent,
            'mock_id' => $mockId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return ProviderResult::success(
            providerMessageId: $mockId,
            provider: $this->getDriver(),
            rawResponse: [
                'mock' => true,
                'channel' => 'SMS',
                'recipient' => $message->recipient,
                'dispatched_at' => now()->toIso8601String(),
                'status' => 'ACCEPTED',
            ],
            metadata: [
                'driver' => $this->getDriver(),
                'simulated' => true,
            ]
        );
    }

    public function validateConfiguration(): ProviderHealthResult
    {
        return new ProviderHealthResult(
            configured: true,
            reachable: true,
            authenticated: true,
            latencyMs: 4,
            error: null,
            driver: $this->getDriver(),
            timestamp: now()->toIso8601String()
        );
    }

    public function getName(): string
    {
        return 'Fake SMS Provider (Desenvolvimento)';
    }

    public function getChannel(): string
    {
        return 'SMS';
    }

    public function getDriver(): string
    {
        return 'fake_sms';
    }
}
