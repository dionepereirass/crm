<?php

namespace App\Services\Providers;

use App\Models\Provider;
use Illuminate\Support\Facades\Redis;

class CircuitBreakerService
{
    protected const THRESHOLD = 5; // 5 falhas consecutivas abrem o circuito
    protected const COOLDOWN_SECONDS = 60; // 60 segundos de janela de resfriamento

    /**
     * Verifica se o circuito para o provedor está disponível para receber tráfego.
     */
    public function isAvailable(Provider $provider): bool
    {
        $key = $this->getKey($provider);

        try {
            $state = Redis::hget($key, 'state') ?: 'CLOSED';
            $openedAt = (int) (Redis::hget($key, 'opened_at') ?: 0);

            if ($state === 'CLOSED') {
                return true;
            }

            if ($state === 'OPEN') {
                // Verifica se a janela de resfriamento expirou para permitir teste em HALF_OPEN
                if ((time() - $openedAt) >= self::COOLDOWN_SECONDS) {
                    Redis::hset($key, 'state', 'HALF_OPEN');
                    return true;
                }
                return false;
            }

            // HALF_OPEN permite envio de teste
            return true;
        } catch (\Throwable) {
            // Em caso de indisponibilidade transitória do Redis, permite operação normal
            return true;
        }
    }

    /**
     * Registra despacho bem-sucedido, fechando o circuito e zerando falhas.
     */
    public function recordSuccess(Provider $provider): void
    {
        $key = $this->getKey($provider);

        try {
            Redis::hset($key, 'state', 'CLOSED');
            Redis::hset($key, 'failures', 0);
            Redis::hdel($key, 'opened_at');
            Redis::expire($key, 86400);
        } catch (\Throwable) {}
    }

    /**
     * Registra falha de envio. Se atingir o limiar, abre o circuito.
     */
    public function recordFailure(Provider $provider, ?string $errorCode = null): void
    {
        // Ignora erros que não são de conectividade/infraestrutura (ex: destinatário inválido não deve derrubar o circuito)
        $nonCircuitBreakerErrors = ['INVALID_RECIPIENT', 'BAD_REQUEST', 'UNAUTHORIZED', 'INVALID_CREDENTIALS'];
        if ($errorCode && in_array($errorCode, $nonCircuitBreakerErrors, true)) {
            return;
        }

        $key = $this->getKey($provider);

        try {
            $failures = (int) Redis::hincrby($key, 'failures', 1);

            if ($failures >= self::THRESHOLD) {
                Redis::hset($key, 'state', 'OPEN');
                Redis::hset($key, 'opened_at', time());
            }

            Redis::expire($key, 86400);
        } catch (\Throwable) {}
    }

    /**
     * Retorna o estado atual do circuito para monitoramento.
     */
    public function getState(Provider $provider): string
    {
        $key = $this->getKey($provider);
        try {
            return Redis::hget($key, 'state') ?: 'CLOSED';
        } catch (\Throwable) {
            return 'CLOSED';
        }
    }

    protected function getKey(Provider $provider): string
    {
        return "betcrm:circuit_breaker:{$provider->platform_id}:{$provider->id}";
    }
}
