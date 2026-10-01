<?php

namespace App\Services\Segments;

use App\Models\Segment;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SegmentCacheService
{
    protected int $defaultTtl = 3600; // 1 hora de cache padrão

    /**
     * Retorna a contagem em cache se existir.
     */
    public function get(int $platformId, int $segmentId): ?int
    {
        $key = $this->buildCacheKey($platformId, $segmentId);

        try {
            $cached = Redis::get($key);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (Throwable $e) {
            // Fallback silencioso se Redis estiver indisponível
        }

        return null;
    }

    /**
     * Armazena a contagem em cache no Redis e atualiza timestamp no modelo.
     */
    public function put(int $platformId, int $segmentId, int $count, ?int $ttl = null): void
    {
        $key = $this->buildCacheKey($platformId, $segmentId);
        $ttlSeconds = $ttl ?? $this->defaultTtl;

        try {
            Redis::setex($key, $ttlSeconds, $count);
        } catch (Throwable $e) {
            // Ignora falha de cache
        }
    }

    /**
     * Invalida o cache de contagem de um segmento específico.
     */
    public function invalidate(int $platformId, int $segmentId): void
    {
        $key = $this->buildCacheKey($platformId, $segmentId);

        try {
            Redis::del($key);
        } catch (Throwable $e) {
            // Ignora falha de cache
        }
    }

    /**
     * Invalidação inteligente orientada a eventos para a plataforma.
     */
    public function invalidateForEvent(int $platformId, string $eventType): void
    {
        $pattern = match (strtoupper($eventType)) {
            'DEPOSIT_SUCCESS' => 'deposit.',
            'BET_PLACED', 'BET_SETTLED' => 'bet.',
            'WITHDRAWAL_SUCCESS' => 'withdrawal.',
            'LOGIN' => 'login.',
            'PLAYER_CREATED', 'PLAYER_UPDATED' => 'player.',
            default => null,
        };

        if (!$pattern) {
            return;
        }

        // Busca segmentos ativos da plataforma que utilizem campos do padrão
        $affectedSegments = Segment::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->where('status', 'ACTIVE')
            ->where('rules_tree', 'LIKE', "%{$pattern}%")
            ->pluck('id');

        foreach ($affectedSegments as $segmentId) {
            $this->invalidate($platformId, $segmentId);
        }
    }

    /**
     * Executa a contagem com memoização no cache Redis.
     */
    public function remember(int $platformId, int $segmentId, callable $callback, ?int $ttl = null): array
    {
        $cached = $this->get($platformId, $segmentId);
        if ($cached !== null) {
            return [
                'count' => $cached,
                'cached' => true,
            ];
        }

        $count = $callback();
        $this->put($platformId, $segmentId, $count, $ttl);

        return [
            'count' => $count,
            'cached' => false,
        ];
    }

    protected function buildCacheKey(int $platformId, int $segmentId): string
    {
        return "betcrm:segment:{$platformId}:{$segmentId}:count";
    }
}
