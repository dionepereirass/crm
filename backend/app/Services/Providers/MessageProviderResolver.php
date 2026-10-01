<?php

namespace App\Services\Providers;

use App\Models\Provider;
use App\Services\Providers\Contracts\MessageProviderInterface;
use InvalidArgumentException;

class MessageProviderResolver
{
    /**
     * Resolve o model Provider e sua respectiva instância MessageProviderInterface configurada.
     *
     * @return array{provider: Provider, driver: MessageProviderInterface}
     */
    public function resolve(int $platformId, string $channel, ?int $providerId = null): array
    {
        $upperChannel = strtoupper($channel);

        // 1. Provedor específico requisitado
        if ($providerId) {
            $provider = Provider::where('platform_id', $platformId)
                ->where('id', $providerId)
                ->with('credentials')
                ->first();

            if (!$provider) {
                throw new InvalidArgumentException("Provedor #{$providerId} não encontrado para a plataforma atual.");
            }

            if (!$provider->isActive()) {
                throw new InvalidArgumentException("O provedor '{$provider->name}' está desativado.");
            }

            if (strtoupper($provider->channel) !== $upperChannel) {
                throw new InvalidArgumentException("O provedor #{$providerId} pertence ao canal {$provider->channel}, incompatível com {$upperChannel}.");
            }

            return [
                'provider' => $provider,
                'driver' => $this->instantiateDriver($provider),
            ];
        }

        // 2. Provedor padrão (is_default = true) ativo para o canal
        $defaultProvider = Provider::where('platform_id', $platformId)
            ->where('channel', $upperChannel)
            ->where('status', 'ACTIVE')
            ->where('is_default', true)
            ->with('credentials')
            ->first();

        if ($defaultProvider) {
            return [
                'provider' => $defaultProvider,
                'driver' => $this->instantiateDriver($defaultProvider),
            ];
        }

        // 3. Provedor ativo de maior prioridade (menor número)
        $priorityProvider = Provider::where('platform_id', $platformId)
            ->where('channel', $upperChannel)
            ->where('status', 'ACTIVE')
            ->orderBy('priority', 'asc')
            ->with('credentials')
            ->first();

        if ($priorityProvider) {
            return [
                'provider' => $priorityProvider,
                'driver' => $this->instantiateDriver($priorityProvider),
            ];
        }

        // 4. Fallback de Desenvolvimento / Testes (Fake Provider) se permitido
        if (app()->environment('local', 'testing', 'dev')) {
            $fakeDriver = $upperChannel === 'EMAIL' ? 'fake_email' : 'fake_sms';

            // Localiza ou cria dinamicamente o provedor fake da plataforma
            $fakeProvider = Provider::firstOrCreate(
                [
                    'platform_id' => $platformId,
                    'driver' => $fakeDriver,
                ],
                [
                    'name' => ($upperChannel === 'EMAIL' ? 'Fake E-mail Padrão' : 'Fake SMS Padrão'),
                    'channel' => $upperChannel,
                    'status' => 'ACTIVE',
                    'is_default' => true,
                    'priority' => 999,
                    'configuration' => ['mock' => true],
                ]
            );

            return [
                'provider' => $fakeProvider,
                'driver' => $this->instantiateDriver($fakeProvider),
            ];
        }

        throw new InvalidArgumentException("Nenhum provedor ativo configurado para o canal {$upperChannel} na plataforma #{$platformId}.");
    }

    /**
     * Instancia o driver associado ao Provider com credenciais seguras e configurações.
     */
    public function instantiateDriver(Provider $provider): MessageProviderInterface
    {
        $credentials = $provider->credentials ? $provider->credentials->getDecryptedCredentials() : [];
        $configuration = $provider->configuration ?? [];

        return ProviderRegistry::make($provider->driver, $credentials, $configuration);
    }
}
