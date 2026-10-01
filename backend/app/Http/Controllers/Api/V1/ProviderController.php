<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\StoreProviderRequest;
use App\Http\Requests\Providers\UpdateProviderRequest;
use App\Http\Resources\ProviderResource;
use App\Models\Platform;
use App\Models\Provider;
use App\Services\Platforms\PlatformContext;
use App\Services\Providers\ProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProviderController extends Controller
{
    public function __construct(
        protected ProviderService $providerService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Lista provedores da plataforma ativa com filtros e paginação.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Provider::class);

        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $paginated = $this->providerService->list(
            $platformId,
            $request->only(['channel', 'status', 'driver', 'search']),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'message' => 'Provedores recuperados com sucesso.',
            'data' => ProviderResource::collection($paginated),
            'pagination' => [
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 200);
    }

    /**
     * Cria um novo provedor na plataforma.
     */
    public function store(StoreProviderRequest $request): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->create($platformId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Provedor configurado com sucesso.',
                'data' => ProviderResource::make($provider),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe os detalhes de um provedor (zero segredos).
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->getById($id, $platformId);
            $this->authorize('view', $provider);

            return response()->json([
                'success' => true,
                'data' => ProviderResource::make($provider),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Atualiza metadados ou credenciais do provedor.
     */
    public function update(UpdateProviderRequest $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->getById($id, $platformId);
            $this->authorize('update', $provider);

            $updated = $this->providerService->update($id, $platformId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Provedor atualizado com sucesso.',
                'data' => ProviderResource::make($updated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrado') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Remove um provedor da plataforma.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->getById($id, $platformId);
            $this->authorize('delete', $provider);

            $this->providerService->delete($id, $platformId);

            return response()->json([
                'success' => true,
                'message' => 'Provedor removido com sucesso.',
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Ativa o provedor.
     */
    public function activate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->getById($id, $platformId);
            $this->authorize('activate', $provider);

            $activated = $this->providerService->activate($id, $platformId);

            return response()->json([
                'success' => true,
                'message' => "Provedor '{$activated->name}' ativado com sucesso.",
                'data' => ProviderResource::make($activated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrado') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Desativa o provedor.
     */
    public function deactivate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->getById($id, $platformId);
            $this->authorize('activate', $provider);

            $deactivated = $this->providerService->deactivate($id, $platformId);

            return response()->json([
                'success' => true,
                'message' => "Provedor '{$deactivated->name}' desativado.",
                'data' => ProviderResource::make($deactivated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrado') ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Executa teste de conectividade e validação da configuração (Health Check).
     */
    public function healthCheck(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $provider = $this->providerService->getById($id, $platformId);
            $this->authorize('health', $provider);

            $health = $this->providerService->healthCheck($id, $platformId);

            return response()->json([
                'success' => $health->isHealthy(),
                'message' => $health->isHealthy() ? 'Conexão e autenticação válidas.' : 'Falha na verificação de conectividade.',
                'data' => $health->toArray(),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'não encontrado') ? 404 : 422;
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
