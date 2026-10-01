<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConsentStatus;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Enums\RetentionAction;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessRetentionPoliciesJob;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\DataSubjectRequest;
use App\Models\Player;
use App\Models\RetentionPolicy;
use App\Services\Privacy\AuditService;
use App\Services\Privacy\ConsentPolicyService;
use App\Services\Privacy\DataExportService;
use App\Services\Privacy\DeletionPolicyService;
use App\Services\Privacy\PersonalDataAccessService;
use App\Services\Privacy\PersonalDataRegistry;
use App\Services\Privacy\PlayerAnonymizationService;
use App\Services\Privacy\RetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PrivacyController extends Controller
{
    public function __construct(
        protected ConsentPolicyService $consentPolicyService,
        protected DataExportService $exportService,
        protected PlayerAnonymizationService $anonymizationService,
        protected DeletionPolicyService $deletionPolicyService,
        protected RetentionService $retentionService,
        protected PersonalDataAccessService $dataAccessService,
        protected PersonalDataRegistry $dataRegistry,
        protected AuditService $auditService
    ) {}

    protected function getPlatformId(Request $request): int
    {
        return (int) ($request->attributes->get('platform_id') ?? $request->header('X-Platform-Id') ?? 1);
    }

    /**
     * Dashboard Geral de Privacidade, Consentimento e LGPD.
     */
    public function dashboard(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Consent::class);
        $platformId = $this->getPlatformId($request);

        $totalPlayers = Player::where('platform_id', $platformId)->count();
        $anonymizedPlayers = Player::where('platform_id', $platformId)->where('status', 'ANONYMIZED')->count();

        $activeConsents = Consent::where('platform_id', $platformId)->where('status', ConsentStatus::GRANTED->value)->count();
        $revokedConsents = Consent::where('platform_id', $platformId)->where('status', ConsentStatus::REVOKED->value)->count();

        $openRequests = DataSubjectRequest::where('platform_id', $platformId)->where('status', DataSubjectRequestStatus::OPEN->value)->count();
        $inProgressRequests = DataSubjectRequest::where('platform_id', $platformId)->where('status', DataSubjectRequestStatus::IN_PROGRESS->value)->count();
        $completedRequests = DataSubjectRequest::where('platform_id', $platformId)->where('status', DataSubjectRequestStatus::COMPLETED->value)->count();

        $nearSlaRequests = DataSubjectRequest::where('platform_id', $platformId)
            ->whereIn('status', [DataSubjectRequestStatus::OPEN->value, DataSubjectRequestStatus::IN_PROGRESS->value])
            ->where('due_at', '>', now())
            ->where('due_at', '<=', now()->addDays(3))
            ->count();

        $expiredRequests = DataSubjectRequest::where('platform_id', $platformId)
            ->whereIn('status', [DataSubjectRequestStatus::OPEN->value, DataSubjectRequestStatus::IN_PROGRESS->value])
            ->where('due_at', '<', now())
            ->count();

        $activePolicies = RetentionPolicy::where('platform_id', $platformId)->where('active', true)->count();

        $recentRequests = DataSubjectRequest::where('platform_id', $platformId)
            ->with('player:id,name,email')
            ->orderBy('requested_at', 'desc')
            ->limit(5)
            ->get();

        $recentAudit = AuditLog::where('platform_id', $platformId)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json([
            'metrics' => [
                'total_players' => $totalPlayers,
                'anonymized_players' => $anonymizedPlayers,
                'active_consents' => $activeConsents,
                'revoked_consents' => $revokedConsents,
                'open_requests' => $openRequests,
                'in_progress_requests' => $inProgressRequests,
                'completed_requests' => $completedRequests,
                'near_sla_requests' => $nearSlaRequests,
                'expired_requests' => $expiredRequests,
                'active_retention_policies' => $activePolicies,
            ],
            'recent_requests' => $recentRequests,
            'recent_audit' => $recentAudit,
            'categories' => $this->dataRegistry->getAllCategories(),
        ]);
    }

    /**
     * Listagem de Consentimentos com Filtros.
     */
    public function consents(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Consent::class);
        $platformId = $this->getPlatformId($request);

        $query = Consent::where('platform_id', $platformId)->with('player:id,name,email,cpf');

        if ($request->filled('type') && $request->type !== 'ALL') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        if ($request->filled('player_id')) {
            $query->where('player_id', (int) $request->player_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('player', function ($q) use ($s) {
                $q->where('name', 'ILIKE', "%{$s}%")
                  ->orWhere('email', 'ILIKE', "%{$s}%")
                  ->orWhere('cpf', 'LIKE', "%{$s}%");
            });
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate((int) $request->get('per_page', 15));

        return response()->json($paginator);
    }

    /**
     * Concede consentimento.
     */
    public function grantConsent(Request $request): JsonResponse
    {
        Gate::authorize('update', Consent::class);
        $platformId = $this->getPlatformId($request);

        $request->validate([
            'player_id' => 'required|integer',
            'type' => 'required|string',
            'source' => 'nullable|string',
            'version' => 'nullable|string',
        ]);

        $player = Player::withoutGlobalScopes()->where('platform_id', $platformId)->findOrFail($request->player_id);

        $consent = $this->consentPolicyService->grantConsent($player, $request->type, [
            'source' => $request->source ?? 'privacy_admin',
            'version' => $request->version ?? 'v1.0',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'actor_type' => 'USER',
            'actor_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Consentimento concedido com sucesso.',
            'data' => $consent,
        ]);
    }

    /**
     * Revoga consentimento.
     */
    public function revokeConsent(Request $request): JsonResponse
    {
        Gate::authorize('update', Consent::class);
        $platformId = $this->getPlatformId($request);

        $request->validate([
            'player_id' => 'required|integer',
            'type' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $player = Player::withoutGlobalScopes()->where('platform_id', $platformId)->findOrFail($request->player_id);

        $consent = $this->consentPolicyService->revokeConsent($player, $request->type, [
            'source' => 'privacy_admin',
            'reason' => $request->reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'actor_type' => 'USER',
            'actor_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Consentimento revogado com sucesso.',
            'data' => $consent,
        ]);
    }

    /**
     * Histórico imutável de um consentimento.
     */
    public function consentHistory(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Consent::class);
        $platformId = $this->getPlatformId($request);

        $consent = Consent::where('platform_id', $platformId)->findOrFail($id);
        $history = $consent->history()->get();

        return response()->json([
            'consent' => $consent,
            'history' => $history,
        ]);
    }

    /**
     * Listagem de Solicitações dos Titulares (DSR).
     */
    public function requests(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $query = DataSubjectRequest::where('platform_id', $platformId)
            ->with(['player:id,name,email,cpf', 'assignedUser:id,name,email']);

        if ($request->filled('type') && $request->type !== 'ALL') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('player', function ($q) use ($s) {
                $q->where('name', 'ILIKE', "%{$s}%")
                  ->orWhere('email', 'ILIKE', "%{$s}%");
            });
        }

        $paginator = $query->orderBy('requested_at', 'desc')->paginate((int) $request->get('per_page', 15));

        return response()->json($paginator);
    }

    /**
     * Criação de nova Solicitação de Titular.
     */
    public function createRequest(Request $request): JsonResponse
    {
        Gate::authorize('create', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $data = $request->validate([
            'player_id' => 'required|integer',
            'type' => 'required|string',
            'reason' => 'nullable|string',
            'requested_by' => 'nullable|string',
        ]);

        $player = Player::withoutGlobalScopes()->where('platform_id', $platformId)->findOrFail($data['player_id']);

        $req = DataSubjectRequest::create([
            'platform_id' => $platformId,
            'player_id' => $player->id,
            'type' => $data['type'],
            'status' => DataSubjectRequestStatus::OPEN->value,
            'reason' => $data['reason'] ?? null,
            'requested_by' => $data['requested_by'] ?? $request->user()?->name ?? 'Titular',
            'requested_at' => now(),
            'due_at' => now()->addDays(15),
        ]);

        $this->auditService->log(
            $platformId,
            'DATA_SUBJECT_REQUEST_CREATED',
            'DataSubjectRequest',
            $req->id,
            null,
            ['type' => $data['type'], 'player_id' => $player->id],
            'USER',
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Solicitação registrada com sucesso.',
            'data' => $req,
        ], 201);
    }

    /**
     * Detalhes de uma Solicitação.
     */
    public function getRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAny', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $req = DataSubjectRequest::where('platform_id', $platformId)
            ->with(['player', 'assignedUser'])
            ->findOrFail($id);

        // Registra acesso ao titular na auditoria
        $this->dataAccessService->logAccess(
            $platformId,
            $request->user()?->id ?? 1,
            $req->player_id,
            'DataSubjectRequest',
            ['name', 'email', 'cpf', 'requests'],
            'Inspeção de solicitação do titular'
        );

        return response()->json(['data' => $req]);
    }

    /**
     * Assumir / Atribuir Solicitação.
     */
    public function assignRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('update', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $req = DataSubjectRequest::where('platform_id', $platformId)->findOrFail($id);
        $userId = $request->input('user_id', $request->user()?->id);

        $req->update(['assigned_to' => $userId]);

        $this->auditService->log(
            $platformId,
            'DATA_SUBJECT_REQUEST_ASSIGNED',
            'DataSubjectRequest',
            $req->id,
            null,
            ['assigned_to' => $userId],
            'USER',
            $request->user()?->id
        );

        return response()->json(['message' => 'Solicitação atribuída.', 'data' => $req]);
    }

    /**
     * Iniciar Processamento da Solicitação.
     */
    public function processRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('process', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $req = DataSubjectRequest::where('platform_id', $platformId)->findOrFail($id);
        $req->update(['status' => DataSubjectRequestStatus::IN_PROGRESS->value]);

        $this->auditService->log(
            $platformId,
            'DATA_SUBJECT_REQUEST_IN_PROGRESS',
            'DataSubjectRequest',
            $req->id,
            ['status' => DataSubjectRequestStatus::OPEN->value],
            ['status' => DataSubjectRequestStatus::IN_PROGRESS->value],
            'USER',
            $request->user()?->id
        );

        return response()->json(['message' => 'Solicitação em processamento.', 'data' => $req]);
    }

    /**
     * Concluir Solicitação.
     */
    public function completeRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('process', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $req = DataSubjectRequest::where('platform_id', $platformId)->findOrFail($id);
        $resolution = $request->input('resolution', 'Solicitação atendida com sucesso.');

        $req->update([
            'status' => DataSubjectRequestStatus::COMPLETED->value,
            'completed_at' => now(),
            'resolution' => $resolution,
        ]);

        $this->auditService->log(
            $platformId,
            'DATA_SUBJECT_REQUEST_COMPLETED',
            'DataSubjectRequest',
            $req->id,
            null,
            ['resolution' => $resolution],
            'USER',
            $request->user()?->id
        );

        return response()->json(['message' => 'Solicitação concluída com sucesso.', 'data' => $req]);
    }

    /**
     * Rejeitar Solicitação com Justificativa Legal.
     */
    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('process', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $request->validate(['resolution' => 'required|string']);

        $req = DataSubjectRequest::where('platform_id', $platformId)->findOrFail($id);
        $req->update([
            'status' => DataSubjectRequestStatus::REJECTED->value,
            'completed_at' => now(),
            'resolution' => $request->resolution,
        ]);

        $this->auditService->log(
            $platformId,
            'DATA_SUBJECT_REQUEST_REJECTED',
            'DataSubjectRequest',
            $req->id,
            null,
            ['resolution' => $request->resolution],
            'USER',
            $request->user()?->id
        );

        return response()->json(['message' => 'Solicitação rejeitada.', 'data' => $req]);
    }

    /**
     * Cancelar Solicitação.
     */
    public function cancelRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('update', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $req = DataSubjectRequest::where('platform_id', $platformId)->findOrFail($id);
        $req->update([
            'status' => DataSubjectRequestStatus::CANCELLED->value,
            'completed_at' => now(),
            'resolution' => $request->input('reason', 'Solicitação cancelada pelo operador.'),
        ]);

        return response()->json(['message' => 'Solicitação cancelada.', 'data' => $req]);
    }

    /**
     * Exportação de Dados do Titular vinculado à Solicitação.
     */
    public function exportRequest(Request $request, int $id): JsonResponse
    {
        Gate::authorize('export', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $req = DataSubjectRequest::where('platform_id', $platformId)->with('player')->findOrFail($id);
        $bundle = $this->exportService->export($req->player, 'json', $request->user()?->id);

        return response()->json([
            'message' => 'Dados exportados com sucesso.',
            'request_id' => $req->id,
            'data' => $bundle,
        ]);
    }

    /**
     * Exportação Direta de Dados de um Jogador.
     */
    public function exportPlayer(Request $request, int $playerId): JsonResponse
    {
        Gate::authorize('export', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $player = Player::withoutGlobalScopes()->where('platform_id', $platformId)->findOrFail($playerId);
        $bundle = $this->exportService->export($player, 'json', $request->user()?->id);

        return response()->json([
            'message' => 'Exportação gerada com sucesso.',
            'data' => $bundle,
        ]);
    }

    /**
     * Anonimização Direta de um Jogador.
     */
    public function anonymizePlayer(Request $request, int $playerId): JsonResponse
    {
        Gate::authorize('delete', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $request->validate(['confirmed' => 'required|boolean']);

        $player = Player::withoutGlobalScopes()->where('platform_id', $platformId)->findOrFail($playerId);

        $this->anonymizationService->anonymize(
            $player,
            (bool) $request->confirmed,
            $request->user()?->id,
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Jogador anonimizado com sucesso.',
            'player_id' => $playerId,
        ]);
    }

    /**
     * Listar Políticas de Retenção.
     */
    public function retentionPolicies(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RetentionPolicy::class);
        $platformId = $this->getPlatformId($request);

        $policies = RetentionPolicy::where('platform_id', $platformId)->get();

        return response()->json(['data' => $policies]);
    }

    /**
     * Criar ou Atualizar Política de Retenção.
     */
    public function saveRetentionPolicy(Request $request): JsonResponse
    {
        Gate::authorize('manage', RetentionPolicy::class);
        $platformId = $this->getPlatformId($request);

        $data = $request->validate([
            'data_category' => 'required|string',
            'retention_days' => 'required|integer|min:1',
            'action' => 'required|string',
            'active' => 'boolean',
        ]);

        $policy = RetentionPolicy::updateOrCreate(
            ['platform_id' => $platformId, 'data_category' => $data['data_category']],
            [
                'retention_days' => $data['retention_days'],
                'action' => $data['action'],
                'active' => $data['active'] ?? true,
            ]
        );

        $this->auditService->log(
            $platformId,
            'RETENTION_POLICY_SAVED',
            'RetentionPolicy',
            $policy->id,
            null,
            $data,
            'USER',
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Política de retenção configurada com sucesso.',
            'data' => $policy,
        ]);
    }

    /**
     * Executar Políticas de Retenção sob demanda.
     */
    public function processRetention(Request $request): JsonResponse
    {
        Gate::authorize('manage', RetentionPolicy::class);
        $platformId = $this->getPlatformId($request);

        // Executa de forma síncrona para relatório imediato ou despacha job
        $summary = $this->retentionService->processPolicies($platformId);

        return response()->json([
            'message' => 'Políticas de retenção executadas com sucesso.',
            'summary' => $summary,
        ]);
    }

    /**
     * Consulta da Trilha de Auditoria com Filtros e Sanitização.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DataSubjectRequest::class);
        $platformId = $this->getPlatformId($request);

        $query = AuditLog::where('platform_id', $platformId);

        if ($request->filled('action')) {
            $query->where('action', 'LIKE', "%{$request->action}%");
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', (string) $request->actor_id);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate((int) $request->get('per_page', 20));

        return response()->json($paginator);
    }
}
