<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAnalyticsExportJob;
use App\Models\ScheduledReport;
use App\Services\Platforms\PlatformContext;
use App\Services\Reports\ReportsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportsService $reportsService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Gera e retorna a prévia dos dados do relatório.
     * GET /api/v1/reports
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'reports.view');
        $platformId = $this->requirePlatformId($request);

        $request->validate([
            'report_type' => 'required|string|in:PLAYERS,FINANCIAL,MARKETING,AUTOMATIONS,PRIVACY',
        ]);

        $reportType = $request->query('report_type');
        $filters = $request->only(['period', 'date_from', 'date_to']);

        $canViewSensitive = $request->user()->isSuperAdmin() || $request->user()->hasPermission('reports.export');
        $data = $this->reportsService->generateReportData($platformId, $reportType, $filters, !$canViewSensitive);

        return response()->json([
            'success' => true,
            'report_type' => $reportType,
            'total_rows' => count($data),
            'data' => $data,
        ]);
    }

    /**
     * Exporta o relatório em arquivo CSV ou despacha job para processamento em background.
     * POST /api/v1/reports/export
     */
    public function export(Request $request): Response|JsonResponse
    {
        $this->ensurePermission($request, 'reports.export');
        $platformId = $this->requirePlatformId($request);

        $request->validate([
            'report_type' => 'required|string|in:PLAYERS,FINANCIAL,MARKETING,AUTOMATIONS,PRIVACY',
            'format' => 'nullable|string|in:CSV,JSON',
            'async' => 'nullable|boolean',
        ]);

        $reportType = $request->input('report_type');
        $format = strtoupper($request->input('format', 'CSV'));
        $filters = $request->only(['period', 'date_from', 'date_to']);
        $isAsync = filter_var($request->input('async', false), FILTER_VALIDATE_BOOLEAN);

        if ($isAsync) {
            $exportId = (string) \Illuminate\Support\Str::uuid();
            GenerateAnalyticsExportJob::dispatch(
                platformId: $platformId,
                reportType: $reportType,
                filters: $filters,
                format: $format,
                userId: $request->user()->id,
                exportId: $exportId
            );

            return response()->json([
                'success' => true,
                'message' => 'Exportação iniciada em segundo plano.',
                'export_id' => $exportId,
            ], 202);
        }

        $data = $this->reportsService->generateReportData($platformId, $reportType, $filters, false);
        $filename = strtolower($reportType) . '_report_' . date('Ymd_His') . '.' . strtolower($format);

        if ($format === 'JSON') {
            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $csv = $this->reportsService->exportToCsv($data);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Lista relatórios agendados da plataforma.
     * GET /api/v1/reports/scheduled
     */
    public function listScheduled(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'reports.view');
        $platformId = $this->requirePlatformId($request);

        $list = ScheduledReport::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['creator:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    /**
     * Cria um novo agendamento de relatório recorrente.
     * POST /api/v1/reports/scheduled
     */
    public function storeScheduled(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'reports.schedule');
        $platformId = $this->requirePlatformId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'report_type' => 'required|string|in:PLAYERS,FINANCIAL,MARKETING,AUTOMATIONS,PRIVACY',
            'frequency' => 'required|string|in:DAILY,WEEKLY,MONTHLY',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'format' => 'nullable|string|in:CSV,JSON',
            'filters' => 'nullable|array',
        ]);

        $nextRun = match ($validated['frequency']) {
            'DAILY' => Carbon::now()->addDay()->startOfDay(),
            'WEEKLY' => Carbon::now()->addWeek()->startOfWeek(),
            'MONTHLY' => Carbon::now()->addMonth()->startOfMonth(),
        };

        $report = ScheduledReport::create([
            'platform_id' => $platformId,
            'name' => $validated['name'],
            'report_type' => $validated['report_type'],
            'frequency' => $validated['frequency'],
            'recipients' => $validated['recipients'],
            'filters' => $validated['filters'] ?? [],
            'format' => $validated['format'] ?? 'CSV',
            'active' => true,
            'next_run_at' => $nextRun,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Relatório recorrente agendado com sucesso.',
            'data' => $report,
        ], 201);
    }

    /**
     * Remove um relatório agendado.
     * DELETE /api/v1/reports/scheduled/{id}
     */
    public function destroyScheduled(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission($request, 'reports.delete');
        $platformId = $this->requirePlatformId($request);

        $report = ScheduledReport::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->findOrFail($id);

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agendamento de relatório removido.',
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
