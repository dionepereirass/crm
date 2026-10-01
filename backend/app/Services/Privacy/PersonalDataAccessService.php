<?php

namespace App\Services\Privacy;

use App\Models\AuditLog;
use Illuminate\Support\Str;

class PersonalDataAccessService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra auditoria de consulta a dados pessoais sensíveis (Art. 37 da LGPD).
     * IMPORTANTE: Não registra os valores reais, apenas os nomes dos campos acessados.
     */
    public function logAccess(
        int $platformId,
        int|string $actorId,
        int $playerId,
        string $resource,
        array $accessedFields,
        ?string $reason = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): AuditLog {
        return $this->auditService->log(
            $platformId,
            'SENSITIVE_DATA_ACCESSED',
            $resource,
            $playerId,
            null,
            [
                'player_id' => $playerId,
                'accessed_fields' => array_values(array_unique($accessedFields)),
                'reason' => $reason ?: 'Consulta operacional de rotina',
            ],
            'USER',
            $actorId,
            $ip ?: request()?->ip(),
            $userAgent ?: request()?->userAgent()
        );
    }
}
