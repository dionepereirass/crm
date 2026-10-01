<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\OperationalAlert;
use App\Services\Alerts\AlertRuleService;
use App\Services\Alerts\OperationalAlertService;
use App\Services\Platforms\PlatformContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(
        protected OperationalAlertService $alertService,
        protected AlertRuleService $ruleService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Lista alertas operacionais da plataforma.
     * GET /api/v1/alerts
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.view');
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['status', 'severity', 'metric']);
        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));

        $list = $this->alertService->listAlerts($platformId, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $list->items(),
            'pagination' => [
                'current_page' => $list->currentPage(),
                'per_page' => $list->perPage(),
                'total' => $list->total(),
                'last_page' => $list->lastPage(),
            ],
        ]);
    }

    /**
     * Reconhece um alerta pelo operador.
     * POST /api/v1/alerts/{id}/acknowledge
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.resolve');
        $platformId = $this->requirePlatformId($request);

        $alert = $this->alertService->acknowledge($platformId, $id, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Alerta reconhecido pelo operador.',
            'data' => $alert,
        ]);
    }

    /**
     * Resolve o alerta com notas de conclusão.
     * POST /api/v1/alerts/{id}/resolve
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.resolve');
        $platformId = $this->requirePlatformId($request);

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $alert = $this->alertService->resolve(
            platformId: $platformId,
            alertId: $id,
            userId: $request->user()->id,
            notes: $request->input('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Alerta resolvido com sucesso.',
            'data' => $alert,
        ]);
    }

    /**
     * Lista regras de alerta da plataforma.
     * GET /api/v1/alerts/rules
     */
    public function listRules(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.view');
        $platformId = $this->requirePlatformId($request);

        $rules = AlertRule::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->withCount(['alerts'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Cria uma nova regra de alerta.
     * POST /api/v1/alerts/rules
     */
    public function storeRule(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.create');
        $platformId = $this->requirePlatformId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'metric' => 'required|string|in:PROVIDER_FAILURES,DELIVERY_DROP,CHURN_INCREASE,INACTIVE_PLAYERS,MESSAGE_QUEUE_BACKLOG,AUTOMATION_FAILURES,DSR_NEAR_SLA,CIRCUIT_BREAKER_OPEN,WEBHOOK_ERRORS',
            'operator' => 'required|string|in:GT,GTE,LT,LTE,EQ',
            'threshold' => 'required|numeric',
            'severity' => 'nullable|string|in:INFO,WARNING,CRITICAL',
            'cooldown_minutes' => 'nullable|integer|min:1',
            'active' => 'nullable|boolean',
        ]);

        $rule = AlertRule::create([
            'platform_id' => $platformId,
            'name' => $validated['name'],
            'metric' => $validated['metric'],
            'operator' => $validated['operator'],
            'threshold' => $validated['threshold'],
            'severity' => $validated['severity'] ?? 'WARNING',
            'cooldown_minutes' => $validated['cooldown_minutes'] ?? 60,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Regra de alerta criada com sucesso.',
            'data' => $rule,
        ], 201);
    }

    /**
     * Atualiza uma regra de alerta.
     * PUT /api/v1/alerts/rules/{id}
     */
    public function updateRule(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.update');
        $platformId = $this->requirePlatformId($request);

        $rule = AlertRule::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'operator' => 'sometimes|string|in:GT,GTE,LT,LTE,EQ',
            'threshold' => 'sometimes|numeric',
            'severity' => 'sometimes|string|in:INFO,WARNING,CRITICAL',
            'cooldown_minutes' => 'sometimes|integer|min:1',
            'active' => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Regra de alerta atualizada.',
            'data' => $rule->fresh(),
        ]);
    }

    /**
     * Remove uma regra de alerta.
     * DELETE /api/v1/alerts/rules/{id}
     */
    public function destroyRule(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.delete');
        $platformId = $this->requirePlatformId($request);

        $rule = AlertRule::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->findOrFail($id);

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Regra de alerta removida.',
        ]);
    }

    /**
     * Dispara a avaliação imediata das regras de alerta.
     * POST /api/v1/alerts/evaluate
     */
    public function evaluate(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'alerts.resolve');
        $platformId = $this->requirePlatformId($request);

        $generated = $this->ruleService->evaluateRules($platformId);
        $evaluatedCount = AlertRule::where('platform_id', $platformId)->where('active', true)->count();

        return response()->json([
            'success' => true,
            'message' => 'Regras de alerta avaliadas com sucesso.',
            'triggered_alerts_count' => count($generated),
            'data' => [
                'evaluated_rules' => $evaluatedCount,
                'new_alerts_count' => count($generated),
                'alerts' => $generated,
            ],
        ]);
    }

    // ==========================================
    // Helpers
    // ==========================================

    protected function requirePlatformId(Request $request): int
    {
        $platformId = $request->input('platform_id')
            ?? $this->platformContext->getPlatformId()
            ?? ($request->header('X-Platform-Id') ? (int) $request->header('X-Platform-Id') : null);

        if (!$platformId) {
            abort(400, 'Nenhuma plataforma ativa identificada na sessão.');
        }
        return $platformId;
    }

    protected function ensurePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Não autenticado.');
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            return;
        }

        if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission)) {
            return;
        }

        abort(403, "Acesso não autorizado: permissão '{$permission}' necessária.");
    }
}
