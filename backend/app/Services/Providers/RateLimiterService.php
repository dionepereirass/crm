<?php

namespace App\Services\Providers;

use App\Models\Provider;
use Illuminate\Support\Facades\Redis;

class RateLimiterService
{
    /**
     * Tenta consumir uma cota de taxa de envio para o provedor.
     * Retorna true se permitido, false se excedeu o limite.
     */
    public function attempt(Provider $provider): bool
    {
        $limit = $provider->rate_limit_per_minute ?: 60;
        $key = $this->getKey($provider);

        try {
            $current = (int) Redis::incr($key);
            if ($current === 1) {
                Redis::expire($key, 60);
            }

            return $current <= $limit;
        } catch (\Throwable) {
            // Em caso de falha de conexão do Redis, permite operação normal
            return true;
        }
    }

    /**
     * Retorna a quantidade de envios restantes na janela de 1 minuto atual.
     */
    public function remaining(Provider $provider): int
    {
        $limit = $provider->rate_limit_per_minute ?: 60;
        $key = $this->getKey($provider);

        try {
            $current = (int) (Redis::get($key) ?: 0);
            return max(0, $limit - $current);
        } catch (\Throwable) {
            return $limit;
        }
    }

    protected function getKey(Provider $provider): string
    {
        $minuteWindow = floor(time() / 60);
        return "betcrm:rate_limit:{$provider->platform_id}:{$provider->id}:{$minuteWindow}";
    }
}
