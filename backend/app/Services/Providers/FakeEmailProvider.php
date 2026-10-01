<?php

namespace App\Services\Providers;

use App\DTOs\Messaging\EmailMessageDTO;
use App\DTOs\Messaging\HealthCheckResultDTO;
use App\DTOs\Messaging\MessageStatusDTO;
use App\DTOs\Messaging\ProviderSendResultDTO;
use App\DTOs\Messaging\SmsMessageDTO;
use App\Services\Providers\Contracts\MessageProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeEmailProvider implements MessageProviderInterface
{
    public function send(\App\DTOs\Providers\MessagePayload $payload): \App\DTOs\Providers\ProviderResult
    {
        $mockId = 'fake_email_' . Str::uuid()->toString();
        return \App\DTOs\Providers\ProviderResult::sent($mockId, ['mock' => true]);
    }

    public function validateConfiguration(): \App\DTOs\Providers\ProviderHealthResult
    {
        return \App\DTOs\Providers\ProviderHealthResult::healthy('Fake Email Provider operational');
    }

    public function getDriver(): string
    {
        return 'fake_email';
    }

    public function getChannel(): string
    {
        return 'EMAIL';
    }

    public function getName(): string
    {
        return 'Fake Email Provider';
    }

    public function sendEmail(EmailMessageDTO $message): ProviderSendResultDTO
    {
        $mockId = 'fake_email_' . Str::uuid()->toString();

        Log::info('[MOCK PROVIDER] Simulating Email dispatch', [
            'to' => $message->to,
            'subject' => $message->subject,
            'mock_id' => $mockId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return new ProviderSendResultDTO(
            success: true,
            providerMessageId: $mockId,
            provider: 'fake_email',
            status: 'SENT',
            rawResponse: [
                'mock' => true,
                'channel' => 'EMAIL',
                'recipient' => $message->to,
                'dispatched_at' => now()->toIso8601String(),
            ]
        );
    }

    public function sendSms(SmsMessageDTO $message): ProviderSendResultDTO
    {
        throw new \BadMethodCallException('FakeEmailProvider does not support SMS dispatch.');
    }

    public function getMessageStatus(string $providerMessageId): MessageStatusDTO
    {
        return new MessageStatusDTO(
            providerMessageId: $providerMessageId,
            status: 'DELIVERED',
            deliveredAt: now()->toIso8601String(),
            rawDetails: ['mock' => true, 'event' => 'delivered']
        );
    }

    public function validateCredentials(array $credentials): bool
    {
        return true;
    }

    public function healthCheck(): HealthCheckResultDTO
    {
        return new HealthCheckResultDTO(
            provider: 'fake_email',
            healthy: true,
            latencyMs: 1,
            message: 'Fake Email Provider operational (Safe Dev Mode)',
            details: ['mode' => 'mock', 'real_sends_blocked' => true]
        );
    }
}
