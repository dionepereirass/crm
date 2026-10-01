<?php

namespace App\Services\Analytics;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AnalyticsCacheService
{
    protected const DEFAULT_TTL = 300; // 5 minutes

    /**
     * Retorna dados do cache do Redis para a métrica e plataforma informadas, ou calcula via callback.
     */
    public function remember(int $platformId, string $metric, array $filters, ?int $ttl = null, ?Closure $callback = null): mixed
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $key = $this->buildCacheKey($platformId, $metric, $filters);

        if ($callback === null) {
            return Cache::get($key);
        }

        return Cache::remember($key, $ttl, function () use ($callback, $platformId, $metric) {
            $data = $callback();
            $this->trackCacheKey($platformId, $metric, $this->buildCacheKey($platformId, $metric, []));
            return $data;
        });
    }

    /**
     * Invalida chaves de cache de uma métrica específica ou de toda a plataforma.
     */
    public function invalidate(int $platformId, ?string $metric = null): void
    {
        try {
            $pattern = $metric
                ? "betcrm:analytics:{$platformId}:{$metric}:*"
                : "betcrm:analytics:{$platformId}:*";

            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                // Redis::keys pode retornar com prefixo dependendo da config
                foreach ($keys as $k) {
                    Redis::del($k);
                }
            }
        } catch (\Throwable $e) {
            // Em caso de falha de conexão com Redis, fallback silencioso
        }
    }

    /**
     * Constrói a chave canônica segura e isolada por tenant.
     */
    public function buildCacheKey(int $platformId, string $metric, array $filters): string
    {
        ksort($filters);
        $hash = md5(json_encode($filters));
        return "betcrm:analytics:{$platformId}:{$metric}:{$hash}";
    }

    protected function trackCacheKey(int $platformId, string $metric, string $key): void
    {
        // Opcional para rastreamento
    }
}
