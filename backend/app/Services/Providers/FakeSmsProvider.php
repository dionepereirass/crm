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

class FakeSmsProvider implements MessageProviderInterface
{
    public function send(\App\DTOs\Providers\MessagePayload $payload): \App\DTOs\Providers\ProviderResult
    {
        $mockId = 'fake_sms_' . Str::uuid()->toString();
        return \App\DTOs\Providers\ProviderResult::sent($mockId, ['mock' => true]);
    }

    public function validateConfiguration(): \App\DTOs\Providers\ProviderHealthResult
    {
        return \App\DTOs\Providers\ProviderHealthResult::healthy('Fake SMS Provider operational');
    }

    public function getDriver(): string
    {
        return 'fake_sms';
    }

    public function getChannel(): string
    {
        return 'SMS';
    }

    public function getName(): string
    {
        return 'Fake SMS Provider';
    }

    public function sendEmail(EmailMessageDTO $message): ProviderSendResultDTO
    {
        throw new \BadMethodCallException('FakeSmsProvider does not support Email dispatch.');
    }

    public function sendSms(SmsMessageDTO $message): ProviderSendResultDTO
    {
        $mockId = 'fake_sms_' . Str::uuid()->toString();

        Log::info('[MOCK PROVIDER] Simulating SMS dispatch', [
            'to' => $message->to,
            'message_length' => mb_strlen($message->message),
            'mock_id' => $mockId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return new ProviderSendResultDTO(
            success: true,
            providerMessageId: $mockId,
            provider: 'fake_sms',
            status: 'SENT',
            rawResponse: [
                'mock' => true,
                'channel' => 'SMS',
                'recipient' => $message->to,
                'dispatched_at' => now()->toIso8601String(),
            ]
        );
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
            provider: 'fake_sms',
            healthy: true,
            latencyMs: 1,
            message: 'Fake SMS Provider operational (Safe Dev Mode)',
            details: ['mode' => 'mock', 'real_sends_blocked' => true]
        );
    }
}
