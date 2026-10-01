<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\TestMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Platform;
use App\Services\Messaging\MessageService;
use App\Services\Platforms\PlatformContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messageService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Lista mensagens da plataforma com paginação e filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Message::class);

        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $paginated = $this->messageService->list(
            $platformId,
            $request->only(['channel', 'status', 'provider_id', 'search']),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'message' => 'Mensagens recuperadas com sucesso.',
            'data' => MessageResource::collection($paginated),
            'pagination' => [
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 200);
    }

    /**
     * Exibe os detalhes de uma mensagem.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $message = $this->messageService->getById($id, $platformId);
            $this->authorize('view', $message);

            return response()->json([
                'success' => true,
                'data' => MessageResource::make($message),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Retorna a linha do tempo de eventos de entrega da mensagem.
     */
    public function events(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $message = $this->messageService->getById($id, $platformId);
            $this->authorize('view', $message);

            $events = $this->messageService->getEvents($id, $platformId);

            return response()->json([
                'success' => true,
                'data' => $events,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Dispara envio síncrono para teste de mensageria (/api/v1/messages/test).
     */
    public function test(TestMessageRequest $request): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $result = $this->messageService->sendTest(
                $platformId,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Mensagem de teste enviada com sucesso.' : 'Falha no envio da mensagem de teste.',
                'data' => [
                    'message' => MessageResource::make($result['message']),
                    'provider' => $result['provider'],
                    'result' => $result['result'],
                    'latency_ms' => $result['latency_ms'],
                ],
            ], $result['success'] ? 200 : 422);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retenta o envio de uma mensagem com falha.
     */
    public function retry(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $message = $this->messageService->getById($id, $platformId);
            $this->authorize('retry', $message);

            $retried = $this->messageService->retry($id, $platformId);

            return response()->json([
                'success' => true,
                'message' => 'Reenvio da mensagem enfileirado com sucesso.',
                'data' => MessageResource::make($retried),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancela uma mensagem pendente.
     */
    public function cancel(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $message = $this->messageService->getById($id, $platformId);
            $this->authorize('cancel', $message);

            $cancelled = $this->messageService->cancel($id, $platformId);

            return response()->json([
                'success' => true,
                'message' => 'Mensagem cancelada com sucesso.',
                'data' => MessageResource::make($cancelled),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
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
