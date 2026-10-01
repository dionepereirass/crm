<?php

namespace Tests\Unit;

use App\DTOs\Messaging\EmailMessageDTO;
use App\DTOs\Messaging\SmsMessageDTO;
use App\Services\Providers\FakeEmailProvider;
use App\Services\Providers\FakeSmsProvider;
use App\Services\Providers\ProviderManager;
use Tests\TestCase;

class ProviderManagerTest extends TestCase
{
    public function test_resolves_fake_email_provider_in_dev_mode(): void
    {
        $manager = new ProviderManager();
        $provider = $manager->resolve('EMAIL');

        $this->assertInstanceOf(FakeEmailProvider::class, $provider);

        $dto = new EmailMessageDTO(
            to: 'test@example.com',
            subject: 'Teste BET CRM',
            htmlContent: '<p>Mensagem de Teste</p>'
        );

        $result = $provider->sendEmail($dto);

        $this->assertTrue($result->success);
        $this->assertEquals('fake_email', $result->provider);
        $this->assertEquals('SENT', $result->status);
        $this->assertStringStartsWith('fake_email_', $result->providerMessageId);
    }

    public function test_resolves_fake_sms_provider_in_dev_mode(): void
    {
        $manager = new ProviderManager();
        $provider = $manager->resolve('SMS');

        $this->assertInstanceOf(FakeSmsProvider::class, $provider);

        $dto = new SmsMessageDTO(
            to: '5511999998888',
            message: 'Seu código BET CRM é 123456'
        );

        $result = $provider->sendSms($dto);

        $this->assertTrue($result->success);
        $this->assertEquals('fake_sms', $result->provider);
        $this->assertEquals('SENT', $result->status);
        $this->assertStringStartsWith('fake_sms_', $result->providerMessageId);
    }

    public function test_fake_email_provider_rejects_sms(): void
    {
        $provider = new FakeEmailProvider();

        $this->expectException(\BadMethodCallException::class);
        $provider->sendSms(new SmsMessageDTO(to: '123', message: 'test'));
    }

    public function test_fake_sms_provider_rejects_email(): void
    {
        $provider = new FakeSmsProvider();

        $this->expectException(\BadMethodCallException::class);
        $provider->sendEmail(new EmailMessageDTO(to: 'test@example.com', subject: 'test', htmlContent: 'test'));
    }
}
