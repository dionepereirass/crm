<?php

namespace App\Services\Providers\Drivers;

use App\DTOs\Providers\MessagePayload;
use App\DTOs\Providers\ProviderHealthResult;
use App\DTOs\Providers\ProviderResult;
use App\Services\Providers\Contracts\EmailProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeEmailProvider implements EmailProviderInterface
{
    public function __construct(
        protected array $credentials = [],
        protected array $configuration = []
    ) {}

    public function send(MessagePayload $message): ProviderResult
    {
        $mockId = 'fake_email_' . Str::uuid()->toString();

        Log::info('[MOCK EMAIL DISPATCH] Simulating email delivery', [
            'to' => $message->recipient,
            'subject' => $message->subject,
            'mock_id' => $mockId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return ProviderResult::success(
            providerMessageId: $mockId,
            provider: $this->getDriver(),
            rawResponse: [
                'mock' => true,
                'channel' => 'EMAIL',
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
            latencyMs: 5,
            error: null,
            driver: $this->getDriver(),
            timestamp: now()->toIso8601String()
        );
    }

    public function getName(): string
    {
        return 'Fake E-mail Provider (Desenvolvimento)';
    }

    public function getChannel(): string
    {
        return 'EMAIL';
    }

    public function getDriver(): string
    {
        return 'fake_email';
    }
}
