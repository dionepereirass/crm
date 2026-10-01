<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Analytics\AnalyticsCacheService;
use App\Services\Analytics\AutomationAnalyticsService;
use App\Services\Analytics\BettingAnalyticsService;
use App\Services\Analytics\CampaignAnalyticsService;
use App\Services\Analytics\DashboardAnalyticsService;
use App\Services\Analytics\FinancialAnalyticsService;
use App\Services\Analytics\MarketingAnalyticsService;
use App\Services\Analytics\PlayerAnalyticsService;
use App\Services\Analytics\PrivacyAnalyticsService;
use App\Services\Analytics\ProviderAnalyticsService;
use App\Services\Analytics\SegmentAnalyticsService;
use App\Services\Analytics\TemplateAnalyticsService;
use App\Services\Platforms\PlatformContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        protected CampaignAnalyticsService $campaignAnalyticsService,
        protected DashboardAnalyticsService $dashboardService,
        protected PlayerAnalyticsService $playerAnalyticsService,
        protected FinancialAnalyticsService $financialAnalyticsService,
        protected BettingAnalyticsService $bettingAnalyticsService,
        protected MarketingAnalyticsService $marketingAnalyticsService,
        protected TemplateAnalyticsService $templateAnalyticsService,
        protected ProviderAnalyticsService $providerAnalyticsService,
        protected AutomationAnalyticsService $automationAnalyticsService,
        protected PrivacyAnalyticsService $privacyAnalyticsService,
        protected SegmentAnalyticsService $segmentAnalyticsService,
        protected AnalyticsCacheService $cacheService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Dashboard Executivo Consolidado com KPIs, evolução temporal e comparativos.
     * GET /api/v1/analytics/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'analytics.view');
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'grouping', 'date_from', 'date_to', 'inactivity_days']);
        $data = $this->dashboardService->getDashboard($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Visão analítica de Jogadores (Evolução, risco, churn e distribuição).
     * GET /api/v1/analytics/players
     */
    public function players(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.players']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'grouping', 'date_from', 'date_to', 'inactivity_days']);
        $data = $this->playerAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Matriz e Curva de Retenção de Jogadores (D1 a D90 e Cohort).
     * GET /api/v1/analytics/retention
     */
    public function retention(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.players']);
        $platformId = $this->requirePlatformId($request);

        $weeks = (int) $request->query('weeks', 8);
        $cohort = $this->playerAnalyticsService->getCohortAnalysis($platformId, $weeks);
        $curve = $this->playerAnalyticsService->getRetentionCurve($platformId);

        return response()->json([
            'success' => true,
            'data' => [
                'cohort' => $cohort,
                'curve' => $curve['curve'],
            ],
        ]);
    }

    /**
     * Métricas de Churn com limite de inatividade configurável (7, 14, 30, 60 dias).
     * GET /api/v1/analytics/churn
     */
    public function churn(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.players']);
        $platformId = $this->requirePlatformId($request);

        $days = (int) $request->query('inactivity_days', 30);
        $churn = app(\App\Services\Analytics\ChurnService::class)->getChurnMetrics($platformId, $days);

        return response()->json([
            'success' => true,
            'data' => $churn,
        ]);
    }

    /**
     * Métricas Financeiras (Depósitos, saques, saldo líquido, ticket médio, FTDs).
     * GET /api/v1/analytics/finance
     */
    public function finance(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.finance']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'grouping', 'date_from', 'date_to']);
        $data = $this->financialAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Métricas de Apostas e Jogos (Turnover, apostas ganhas/perdidas, win rate).
     * GET /api/v1/analytics/betting
     */
    public function betting(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.betting']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'grouping', 'date_from', 'date_to']);
        $data = $this->bettingAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Métricas de Marketing e Mensageria (Funil completo, CTR, rankings operacionais).
     * GET /api/v1/analytics/marketing
     */
    public function marketing(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.marketing']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $data = $this->marketingAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Comparação de Desempenho Operacional de Templates (E-mail e SMS).
     * GET /api/v1/analytics/templates
     */
    public function templates(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.marketing']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $data = $this->templateAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Monitoramento de Saúde e Desempenho de Provedores de Mensageria.
     * GET /api/v1/analytics/providers
     */
    public function providers(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.providers']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $data = $this->providerAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Métricas de Automações, Jornadas e Passos Executados.
     * GET /api/v1/analytics/automations
     */
    public function automations(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.automations']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $data = $this->automationAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Indicadores de Conformidade LGPD e Governança de Dados.
     * GET /api/v1/analytics/privacy
     */
    public function privacy(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'analytics.privacy', 'privacy.view']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $data = $this->privacyAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Métricas de Tamanho e Utilização de Segmentos Dinâmicos.
     * GET /api/v1/analytics/segments
     */
    public function segments(Request $request): JsonResponse
    {
        $this->ensureAnyPermission($request, ['analytics.view', 'segments.view']);
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['period']);
        $data = $this->segmentAnalyticsService->getAnalytics($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Invalidação forçada do cache de analytics da plataforma.
     * POST /api/v1/analytics/cache/invalidate
     */
    public function invalidateCache(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'analytics.view');
        $platformId = $this->requirePlatformId($request);

        $metric = $request->input('metric');
        $this->cacheService->invalidate($platformId, $metric);

        return response()->json([
            'success' => true,
            'message' => 'Cache de analytics invalidado com sucesso.',
        ]);
    }

    // ==========================================
    // Métodos Originais de Campanhas (Fase 9)
    // ==========================================

    public function campaignAnalytics(Request $request, int $id): JsonResponse
    {
        $platformId = $this->requirePlatformId($request);
        $campaign = Campaign::where('platform_id', $platformId)->findOrFail($id);
        $this->authorize('analytics', $campaign);

        $data = $this->campaignAnalyticsService->getCampaignAnalytics($campaign->id, $platformId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function campaignEvents(Request $request, int $id): JsonResponse
    {
        $platformId = $this->requirePlatformId($request);
        $campaign = Campaign::where('platform_id', $platformId)->findOrFail($id);
        $this->authorize('events', $campaign);

        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));
        $events = $this->campaignAnalyticsService->getCampaignEvents($campaign->id, $platformId, $perPage);

        return response()->json([
            'success' => true,
            'data' => $events->items(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    public function campaignRebuild(Request $request, int $id): JsonResponse
    {
        $platformId = $this->requirePlatformId($request);
        $campaign = Campaign::where('platform_id', $platformId)->findOrFail($id);
        $this->authorize('update', $campaign);

        $metric = $this->campaignAnalyticsService->rebuildForCampaign($campaign->id, $platformId);

        return response()->json([
            'success' => true,
            'message' => 'Métricas da campanha reconstruídas com sucesso.',
            'data' => $metric,
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'analytics.view');
        $platformId = $this->requirePlatformId($request);
        $filters = $request->only(['date_from', 'date_to']);

        $overview = $this->campaignAnalyticsService->getOverview($platformId, $filters);

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'analytics.view');
        $platformId = $this->requirePlatformId($request);
        $filters = $request->only(['channel', 'status', 'provider_id', 'date_from', 'date_to']);
        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));

        $list = $this->campaignAnalyticsService->listCampaignsAnalytics($platformId, $filters, $perPage);

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

    public function export(Request $request): Response
    {
        $this->ensurePermission($request, 'analytics.export');
        $platformId = $this->requirePlatformId($request);

        $filters = $request->only(['channel', 'status', 'provider_id', 'date_from', 'date_to']);
        $csvContent = $this->campaignAnalyticsService->exportCsv($platformId, $filters);
        $filename = 'campaigns_analytics_' . date('Ymd_His') . '.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // ==========================================
    // Helpers de Plataforma & RBAC
    // ==========================================

    protected function requirePlatformId(Request $request): int
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            abort(400, 'Nenhuma plataforma ativa identificada na sessão.');
        }
        return $platformId;
    }

    protected function resolvePlatformId(Request $request): ?int
    {
        return $request->input('platform_id')
            ?? $this->platformContext->getPlatformId()
            ?? ($request->header('X-Platform-Id') ? (int) $request->header('X-Platform-Id') : null);
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

    protected function ensureAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Não autenticado.');
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        foreach ($permissions as $p) {
            if (method_exists($user, 'hasPermission') && $user->hasPermission($p)) {
                return;
            }
            if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($p)) {
                return;
            }
        }

        $permList = implode(', ', $permissions);
        abort(403, "Acesso não autorizado: requer ao menos uma das permissões ({$permList}).");
    }
}
