<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\WebhookIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookIngestionService $ingestionService
    ) {}

    /**
     * Ponto de entrada de Webhooks da plataforma.
     * Recebe, valida assinatura HMAC-SHA256, checa idempotência e enfileira para processamento.
     */
    public function handle(string $platform_slug, Request $request): JsonResponse
    {
        try {
            $result = $this->ingestionService->ingest($platform_slug, $request);

            return response()->json($result['body'], $result['status_code']);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar webhook.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
