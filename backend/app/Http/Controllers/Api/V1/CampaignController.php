<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\CampaignRecipientResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\MessageResource;
use App\Services\Campaigns\CampaignService;
use App\Services\Platforms\PlatformContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Lista campanhas paginadas da plataforma ativa.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Campaign::class);

        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $filters = $request->only(['channel', 'status', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $campaigns = $this->campaignService->list($platformId, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => CampaignResource::collection($campaigns->items()),
            'pagination' => [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
            ],
        ]);
    }

    /**
     * Cria uma nova campanha para a plataforma ativa.
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->create($platformId, $request->validated(), $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Campanha criada com sucesso.',
                'data' => CampaignResource::make($campaign),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe os detalhes de uma campanha.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('view', $campaign);

            return response()->json([
                'success' => true,
                'data' => CampaignResource::make($campaign),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Atualiza dados de uma campanha existente.
     */
    public function update(UpdateCampaignRequest $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('update', $campaign);

            $updated = $this->campaignService->update($id, $platformId, $request->validated(), $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Campanha atualizada com sucesso.',
                'data' => CampaignResource::make($updated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Exclui (soft delete) uma campanha.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('delete', $campaign);

            $this->campaignService->delete($id, $platformId, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Campanha excluída com sucesso.',
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Valida integralmente os pré-requisitos de disparo da campanha.
     */
    public function validateCampaign(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('validate', $campaign);

            $validation = $this->campaignService->validateCampaign($id, $platformId);

            return response()->json([
                'success' => $validation['is_valid'],
                'message' => $validation['is_valid'] ? 'Campanha validada e pronta para disparo.' : 'A campanha contém erros que impedem o disparo.',
                'data' => $validation,
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Gera uma pré-visualização renderizada da mensagem.
     */
    public function preview(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('preview', $campaign);

            $samplePlayerId = $request->input('player_id') ? (int) $request->input('player_id') : null;
            $preview = $this->campaignService->preview($id, $platformId, $samplePlayerId);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Realiza um envio de teste para o e-mail ou telefone especificado pelo operador.
     */
    public function test(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $request->validate([
            'recipient' => ['required', 'string'],
        ], [
            'recipient.required' => 'O destinatário de teste é obrigatório.',
        ]);

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('test', $campaign);

            $result = $this->campaignService->testSend(
                $id,
                $platformId,
                $request->input('recipient'),
                $request->user()?->id
            );

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Inicia o disparo ou agenda a campanha com confirmação explícita.
     */
    public function launch(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $confirmed = $request->boolean('confirmation');
        if (!$confirmed) {
            return response()->json([
                'success' => false,
                'message' => 'É necessária a confirmação explícita (confirmation: true) para disparar a campanha.',
            ], 422);
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('launch', $campaign);

            $launched = $this->campaignService->launch($id, $platformId, true, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => $launched->isScheduled()
                    ? "Campanha agendada com sucesso para {$launched->scheduled_at->toIso8601String()}."
                    : "Campanha iniciada com sucesso. Mensagens sendo processadas na fila.",
                'data' => CampaignResource::make($launched),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Pausa uma campanha em processamento.
     */
    public function pause(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('pause', $campaign);

            $paused = $this->campaignService->pause($id, $platformId, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => "Campanha pausada com sucesso.",
                'data' => CampaignResource::make($paused),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Retoma o processamento de uma campanha pausada.
     */
    public function resume(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('resume', $campaign);

            $resumed = $this->campaignService->resume($id, $platformId, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => "Campanha retomada com sucesso.",
                'data' => CampaignResource::make($resumed),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Cancela uma campanha.
     */
    public function cancel(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('cancel', $campaign);

            $cancelled = $this->campaignService->cancel($id, $platformId, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => "Campanha cancelada com sucesso.",
                'data' => CampaignResource::make($cancelled),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Retorna a lista de destinatários do snapshot da campanha (LGPD masked).
     */
    public function recipients(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('recipients', $campaign);

            $filters = $request->only(['status', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $recipients = $this->campaignService->getRecipients($id, $platformId, $filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => CampaignRecipientResource::collection($recipients->items()),
                'pagination' => [
                    'total' => $recipients->total(),
                    'per_page' => $recipients->perPage(),
                    'current_page' => $recipients->currentPage(),
                    'last_page' => $recipients->lastPage(),
                ],
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Retorna as mensagens geradas pela campanha.
     */
    public function messages(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('messages', $campaign);

            $filters = $request->only(['status']);
            $perPage = (int) $request->input('per_page', 15);

            $messages = $this->campaignService->getMessages($id, $platformId, $filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages->items()),
                'pagination' => [
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                ],
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Retorna estatísticas de entrega e progresso da campanha em tempo real.
     */
    public function stats(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $campaign = $this->campaignService->getById($id, $platformId);
            $this->authorize('stats', $campaign);

            $stats = $this->campaignService->getStats($id, $platformId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrada') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    protected function resolvePlatformId(Request $request): ?int
    {
        return $request->input('platform_id') ?? $this->platformContext->getPlatformId() ?? ($request->header('X-Platform-Id') ? (int) $request->header('X-Platform-Id') : null);
    }

    protected function noPlatformResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Nenhuma plataforma ativa identificada na sessão.',
        ], 400);
    }
}
