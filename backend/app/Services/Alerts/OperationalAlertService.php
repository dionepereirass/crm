<?php

namespace App\Services\Alerts;

use App\Models\OperationalAlert;
use App\Services\Privacy\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OperationalAlertService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Lista alertas da plataforma com paginação e filtros de status e severidade.
     */
    public function listAlerts(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = OperationalAlert::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['rule', 'acknowledgedByUser:id,name', 'resolvedByUser:id,name']);

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['severity']) && $filters['severity'] !== 'ALL') {
            $query->where('severity', strtoupper($filters['severity']));
        }

        if (!empty($filters['metric'])) {
            $query->where('metric', $filters['metric']);
        }

        return $query->orderBy('triggered_at', 'desc')->paginate($perPage);
    }

    /**
     * Reconhece o alerta pelo operador em plantão.
     */
    public function acknowledge(int $platformId, int $alertId, int $userId): OperationalAlert
    {
        $alert = OperationalAlert::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->findOrFail($alertId);

        $alert->update([
            'status' => 'ACKNOWLEDGED',
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);

        $this->auditService->log(
            platformId: $platformId,
            action: 'ACKNOWLEDGE_ALERT',
            resourceType: 'OperationalAlert',
            resourceId: (string) $alert->id,
            newValues: ['status' => 'ACKNOWLEDGED', 'acknowledged_by' => $userId]
        );

        return $alert->fresh();
    }

    /**
     * Resolve o alerta com notas operacionais de solução.
     */
    public function resolve(int $platformId, int $alertId, int $userId, ?string $notes = null): OperationalAlert
    {
        $alert = OperationalAlert::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->findOrFail($alertId);

        $alert->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);

        $this->auditService->log(
            platformId: $platformId,
            action: 'RESOLVE_ALERT',
            resourceType: 'OperationalAlert',
            resourceId: (string) $alert->id,
            newValues: ['status' => 'RESOLVED', 'resolved_by' => $userId, 'notes' => $notes]
        );

        return $alert->fresh();
    }
}
