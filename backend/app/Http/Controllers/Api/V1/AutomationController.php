<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Services\Automations\AutomationGraphValidator;
use App\Services\Automations\AutomationService;
use App\Services\Platforms\PlatformContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AutomationController extends Controller
{
    public function __construct(
        protected AutomationService $automationService,
        protected AutomationGraphValidator $graphValidator,
        protected PlatformContext $platformContext
    ) {}

    protected function resolvePlatformId(Request $request): int
    {
        $platformId = $request->input('platform_id')
            ?? $this->platformContext->getPlatformId()
            ?? ($request->header('X-Platform-Id') ? (int) $request->header('X-Platform-Id') : null)
            ?? $request->user()?->platforms()->first()?->id;

        if (!$platformId) {
            abort(400, 'Contexto de plataforma não especificado.');
        }

        return (int) $platformId;
    }

    /**
     * Lista automações da plataforma.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Automation::class);

        $platformId = $this->resolvePlatformId($request);
        $filters = $request->only(['status', 'trigger_type', 'search']);
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $automations = $this->automationService->list($platformId, $filters, $perPage);

        return response()->json($automations);
    }

    /**
     * Cria nova automação.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Automation::class);

        $platformId = $this->resolvePlatformId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|string',
            'settings' => 'nullable|array',
        ]);

        $automation = $this->automationService->create($platformId, $validated, $request->user()?->id);

        return response()->json([
            'message' => 'Automação criada com sucesso.',
            'data' => $automation,
        ], 201);
    }

    protected function getAuthorizedAutomation(Request $request, int $id, string $ability = 'view'): Automation
    {
        $platformId = $this->resolvePlatformId($request);
        $automation = Automation::withoutGlobalScopes()->find($id);

        if (!$automation) {
            abort(404, "Automação #{$id} não encontrada.");
        }

        Gate::authorize($ability, $automation);

        if ($automation->platform_id !== $platformId) {
            abort(403, 'Acesso não autorizado para esta plataforma.');
        }

        return $automation;
    }

    /**
     * Exibe detalhes da automação.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'view');

        return response()->json([
            'data' => $automation->load(['nodes', 'edges', 'creator', 'updater']),
            'metrics' => $this->automationService->getMetrics($automation),
        ]);
    }

    /**
     * Atualiza dados da automação.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'update');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'sometimes|string',
            'settings' => 'nullable|array',
        ]);

        $updated = $this->automationService->update($automation, $validated, $request->user()?->id);

        return response()->json([
            'message' => 'Automação atualizada com sucesso.',
            'data' => $updated,
        ]);
    }

    /**
     * Exclui automação.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'delete');

        $this->automationService->delete($automation, $request->user()?->id);

        return response()->json([
            'message' => 'Automação excluída com sucesso.',
        ]);
    }

    /**
     * Ativa a automação.
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'activate');

        $activated = $this->automationService->activate($automation, $request->user()?->id);

        return response()->json([
            'message' => 'Automação ativada com sucesso.',
            'data' => $activated,
        ]);
    }

    /**
     * Pausa a automação.
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'pause');

        $paused = $this->automationService->pause($automation, $request->user()?->id);

        return response()->json([
            'message' => 'Automação pausada com sucesso.',
            'data' => $paused,
        ]);
    }

    /**
     * Desativa a automação.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'update');

        $deactivated = $this->automationService->deactivate($automation, $request->user()?->id);

        return response()->json([
            'message' => 'Automação desativada com sucesso.',
            'data' => $deactivated,
        ]);
    }

    /**
     * Obtém o grafo da automação (nós e conexões).
     */
    public function getGraph(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'view');

        return response()->json($this->automationService->getGraph($automation));
    }

    /**
     * Salva o grafo da automação.
     */
    public function saveGraph(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'graph');

        $validated = $request->validate([
            'nodes' => 'required|array',
            'nodes.*.node_key' => 'required|string',
            'nodes.*.node_type' => 'required|string',
            'nodes.*.name' => 'nullable|string',
            'nodes.*.configuration' => 'nullable|array',
            'nodes.*.position_x' => 'nullable|numeric',
            'nodes.*.position_y' => 'nullable|numeric',
            'edges' => 'present|array',
            'edges.*.source_node_key' => 'nullable|string',
            'edges.*.target_node_key' => 'nullable|string',
            'edges.*.source_node_id' => 'nullable|integer',
            'edges.*.target_node_id' => 'nullable|integer',
            'edges.*.condition_key' => 'nullable|string',
        ]);

        $saved = $this->automationService->saveGraph(
            $automation,
            $validated['nodes'],
            $validated['edges'],
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Grafo da automação salvo com sucesso.',
            'data' => $this->automationService->getGraph($saved),
        ]);
    }

    /**
     * Valida o grafo da automação.
     */
    public function validateGraph(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'view');

        $result = $this->graphValidator->validate($automation);

        return response()->json($result);
    }

    /**
     * Gera prévia visual da jornada.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'view');

        $preview = $this->automationService->preview($automation);

        return response()->json($preview);
    }

    /**
     * Lista execuções da automação.
     */
    public function runs(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'runs');

        $filters = $request->only(['status']);
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $runs = $this->automationService->listRuns($automation, $filters, $perPage);

        return response()->json($runs);
    }

    /**
     * Exibe detalhes de uma execução com seus passos.
     */
    public function showRun(Request $request, int $id, int $runId): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'runs');

        $run = AutomationRun::where('automation_id', $automation->id)
            ->where('id', $runId)
            ->with(['player:id,name,email,phone', 'steps.node', 'logs'])
            ->firstOrFail();

        Gate::authorize('view', $run);

        return response()->json([
            'data' => $run,
        ]);
    }

    /**
     * Cancela uma execução em andamento.
     */
    public function cancelRun(Request $request, int $id, int $runId): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'runs');

        $run = AutomationRun::where('automation_id', $automation->id)
            ->where('id', $runId)
            ->firstOrFail();

        Gate::authorize('cancel', $run);

        $cancelled = $this->automationService->cancelRun($run, $request->user()?->id);

        return response()->json([
            'message' => 'Execução cancelada com sucesso.',
            'data' => $cancelled,
        ]);
    }

    /**
     * Métricas da automação.
     */
    public function metrics(Request $request, int $id): JsonResponse
    {
        $automation = $this->getAuthorizedAutomation($request, $id, 'view');

        return response()->json([
            'metrics' => $this->automationService->getMetrics($automation),
        ]);
    }
}
