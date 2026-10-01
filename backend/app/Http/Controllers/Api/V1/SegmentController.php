<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Segments\PreviewSegmentRequest;
use App\Http\Requests\Segments\StoreSegmentRequest;
use App\Http\Requests\Segments\UpdateSegmentRequest;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\SegmentResource;
use App\Models\Segment;
use App\Services\Platforms\PlatformContext;
use App\Services\Segments\SegmentFieldRegistry;
use App\Services\Segments\SegmentOperatorRegistry;
use App\Services\Segments\SegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SegmentController extends Controller
{
    public function __construct(
        protected SegmentService $segmentService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Lista todos os segmentos da plataforma ativa.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Segment::class);

        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $paginated = $this->segmentService->list(
            $platformId,
            $request->only(['status', 'search']),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'message' => 'Segmentos recuperados com sucesso.',
            'data' => SegmentResource::collection($paginated),
            'pagination' => [
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 200);
    }

    /**
     * Cria um novo segmento na plataforma ativa.
     */
    public function store(StoreSegmentRequest $request): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->create(
                $platformId,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Segmento criado com sucesso.',
                'data' => SegmentResource::make($segment),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe os detalhes de um segmento.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('view', $segment);

            return response()->json([
                'success' => true,
                'data' => SegmentResource::make($segment),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Atualiza um segmento existente.
     */
    public function update(UpdateSegmentRequest $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('update', $segment);

            $updated = $this->segmentService->update(
                $segment,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Segmento atualizado com sucesso.',
                'data' => SegmentResource::make($updated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exclui (soft delete) um segmento.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('delete', $segment);

            $this->segmentService->delete($segment);

            return response()->json([
                'success' => true,
                'message' => 'Segmento excluído com sucesso.',
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Ativa um segmento.
     */
    public function activate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('activate', $segment);

            $segment = $this->segmentService->activate($segment);

            return response()->json([
                'success' => true,
                'message' => 'Segmento ativado com sucesso.',
                'data' => SegmentResource::make($segment),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Desativa um segmento.
     */
    public function deactivate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('activate', $segment);

            $segment = $this->segmentService->deactivate($segment);

            return response()->json([
                'success' => true,
                'message' => 'Segmento desativado com sucesso.',
                'data' => SegmentResource::make($segment),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Executa preview dinâmico de regras sem necessidade de salvar.
     */
    public function preview(PreviewSegmentRequest $request): JsonResponse
    {
        $this->authorize('preview', Segment::class);

        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $result = $this->segmentService->preview(
                $platformId,
                $request->input('rules_tree'),
                (int) $request->input('limit', 10)
            );

            return response()->json([
                'success' => true,
                'count' => $result['count'],
                'execution_time_ms' => $result['execution_time_ms'],
                'sample' => PlayerResource::collection($result['sample']),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Executa preview de um segmento existente.
     */
    public function previewExisting(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('view', $segment);

            $result = $this->segmentService->preview(
                $platformId,
                $segment->rules_tree,
                (int) $request->input('limit', 10)
            );

            return response()->json([
                'success' => true,
                'count' => $result['count'],
                'execution_time_ms' => $result['execution_time_ms'],
                'sample' => PlayerResource::collection($result['sample']),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Força a atualização da contagem e renovação do cache Redis.
     */
    public function refresh(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('refresh', $segment);

            $count = $this->segmentService->refreshCount($segment);

            return response()->json([
                'success' => true,
                'message' => 'Contagem do segmento atualizada com sucesso.',
                'cached_count' => $count,
                'cached_at' => $segment->cached_at?->toIso8601String(),
                'data' => SegmentResource::make($segment),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Retorna a lista paginada de jogadores pertencentes ao segmento.
     */
    public function members(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $this->authorize('members', $segment);

            $members = $this->segmentService->getMembers(
                $segment,
                (int) $request->input('per_page', 25)
            );

            return response()->json([
                'success' => true,
                'message' => 'Membros do segmento recuperados com sucesso.',
                'data' => PlayerResource::collection($members),
                'pagination' => [
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                ],
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Clona/duplica um segmento existente.
     */
    public function duplicate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $this->authorize('create', Segment::class);

        try {
            $segment = $this->segmentService->getById($id, $platformId);
            $clone = $this->segmentService->duplicate($segment, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Segmento duplicado com sucesso.',
                'data' => SegmentResource::make($clone),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Catálogo de campos disponíveis para regras de segmentação.
     */
    public function fields(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SegmentFieldRegistry::grouped(),
            'all' => SegmentFieldRegistry::all(),
        ], 200);
    }

    /**
     * Catálogo de operadores suportados pelo motor de segmentação.
     */
    public function operators(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SegmentOperatorRegistry::grouped(),
            'all' => SegmentOperatorRegistry::all(),
        ], 200);
    }

    /**
     * Resolve o ID da plataforma a partir da requisição ou do contexto ativo.
     */
    protected function resolvePlatformId(Request $request): ?int
    {
        return $request->input('platform_id') ?? $this->platformContext->getPlatformId();
    }

    /**
     * Resposta padrão caso nenhuma plataforma tenha sido informada.
     */
    protected function noPlatformResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Nenhuma plataforma identificada no contexto.',
            'errors' => ['platform_id' => ['Plataforma obrigatória.']],
        ], 422);
    }
}
