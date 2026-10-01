<?php

namespace App\Services\Webhooks;

use App\Models\Event;
use Illuminate\Support\Facades\Redis;
use Throwable;

class IdempotencyService
{
    protected int $ttlSeconds = 86400; // 24 horas

    /**
     * Verifica se o evento já foi registrado ou processado para esta plataforma.
     */
    public function isDuplicate(int $platformId, string $externalEventId): bool
    {
        $cacheKey = $this->buildCacheKey($platformId, $externalEventId);

        // 1. Verificação rápida no Redis se disponível
        try {
            $cached = Redis::get($cacheKey);
            if ($cached !== null) {
                return true;
            }
        } catch (Throwable $e) {
            // Continua para o banco se o Redis falhar
        }

        // 2. Verificação definitiva de consistência no PostgreSQL / banco relacional
        return Event::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->where('external_event_id', $externalEventId)
            ->exists();
    }

    /**
     * Registra a chave de idempotência no Redis para prevenir rajadas simultâneas.
     */
    public function markAsReceived(int $platformId, string $externalEventId, string $status = 'processing'): void
    {
        $cacheKey = $this->buildCacheKey($platformId, $externalEventId);

        try {
            Redis::setex($cacheKey, $this->ttlSeconds, $status);
        } catch (Throwable $e) {
            // Ignora falha de cache (banco relacional garante a unicidade via UNIQUE constraint)
        }
    }

    protected function buildCacheKey(int $platformId, string $externalEventId): string
    {
        return "betcrm:idempotency:{$platformId}:{$externalEventId}";
    }
}
