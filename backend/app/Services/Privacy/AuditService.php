<?php

namespace App\Services\Privacy;

use App\Models\AuditLog;
use Illuminate\Support\Str;

class AuditService
{
    public function __construct(
        protected SensitiveDataSanitizer $sanitizer
    ) {}

    /**
     * Registra evento imutável de auditoria com sanitização de credenciais e PII.
     */
    public function log(
        ?int $platformId,
        string $action,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $actorType = 'USER',
        int|string|null $actorId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): AuditLog {
        $sanitizedOld = $oldValues !== null ? $this->sanitizer->sanitize($oldValues) : null;
        $sanitizedNew = $newValues !== null ? $this->sanitizer->sanitize($newValues) : null;

        return AuditLog::create([
            'platform_id' => $platformId,
            'actor_type' => strtoupper($actorType),
            'actor_id' => $actorId ? (string) $actorId : null,
            'action' => strtoupper($action),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId ? (string) $resourceId : null,
            'old_values' => $sanitizedOld,
            'new_values' => $sanitizedNew,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
            'request_id' => request()?->header('X-Request-Id') ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
