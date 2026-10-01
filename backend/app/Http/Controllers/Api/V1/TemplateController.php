<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CreateTemplateVersionRequest;
use App\Http\Requests\Templates\PreviewTemplateRequest;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Http\Resources\TemplateVersionResource;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Platforms\PlatformContext;
use App\Services\Templates\TemplateService;
use App\Services\Templates\TemplateVariableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TemplateController extends Controller
{
    public function __construct(
        protected TemplateService $templateService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * Lista todos os templates da plataforma ativa.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Template::class);

        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        $paginated = $this->templateService->list(
            $platformId,
            $request->only(['channel', 'status', 'category', 'search']),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'message' => 'Templates recuperados com sucesso.',
            'data' => TemplateResource::collection($paginated),
            'pagination' => [
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 200);
    }

    /**
     * Cria um novo template na plataforma ativa.
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->create(
                $platformId,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Template criado com sucesso.',
                'data' => TemplateResource::make($template),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe os detalhes de um template.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('view', $template);

            return response()->json([
                'success' => true,
                'data' => TemplateResource::make($template),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Atualiza metadados ou cria/atualiza versão do template.
     */
    public function update(UpdateTemplateRequest $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('update', $template);

            $updated = $this->templateService->update(
                $template,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Template atualizado com sucesso.',
                'data' => TemplateResource::make($updated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exclui (soft delete) um template.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('delete', $template);

            $this->templateService->delete($template, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Template excluído com sucesso.',
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Ativa um template.
     */
    public function activate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('publish', $template);

            $activated = $this->templateService->activate($template, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Template ativado com sucesso.',
                'data' => TemplateResource::make($activated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Arquiva um template.
     */
    public function archive(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('archive', $template);

            $archived = $this->templateService->archive($template, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Template arquivado com sucesso.',
                'data' => TemplateResource::make($archived),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Duplica um template existente.
     */
    public function duplicate(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('duplicate', $template);

            $clone = $this->templateService->duplicate($template, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Template duplicado com sucesso.',
                'data' => TemplateResource::make($clone),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Lista o histórico de versões de um template.
     */
    public function versions(Request $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('versions', $template);

            $versions = $this->templateService->listVersions($template, (int) $request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => TemplateVersionResource::collection($versions),
                'pagination' => [
                    'per_page' => $versions->perPage(),
                    'total' => $versions->total(),
                    'current_page' => $versions->currentPage(),
                    'last_page' => $versions->lastPage(),
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
     * Cria explicitamente uma nova versão em DRAFT.
     */
    public function storeVersion(CreateTemplateVersionRequest $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('update', $template);

            $version = $this->templateService->createVersion(
                $template,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Nova versão criada com sucesso.',
                'data' => TemplateVersionResource::make($version),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe uma versão específica do histórico.
     */
    public function showVersion(Request $request, int|string $id, int $versionNumber): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('versions', $template);

            $version = TemplateVersion::where('template_id', $template->id)
                ->where('version', $versionNumber)
                ->with(['creator:id,name,email'])
                ->first();

            if (!$version) {
                return response()->json([
                    'success' => false,
                    'message' => "Versão {$versionNumber} não encontrada.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => TemplateVersionResource::make($version),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Publica uma versão específica.
     */
    public function publishVersion(Request $request, int|string $id, int $versionNumber): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('publish', $template);

            $updated = $this->templateService->publishVersion($template, $versionNumber, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => "Versão {$versionNumber} publicada com sucesso.",
                'data' => TemplateResource::make($updated),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Restaura uma versão histórica gerando uma nova versão N+1 publicada.
     */
    public function restoreVersion(Request $request, int|string $id, int $versionNumber): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('restore', $template);

            $restored = $this->templateService->restoreVersion($template, $versionNumber, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => "Versão {$versionNumber} restaurada com sucesso.",
                'data' => TemplateResource::make($restored),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Renderiza o preview com dados contextuais fornecidos ou mock seguro.
     */
    public function preview(PreviewTemplateRequest $request, int|string $id): JsonResponse
    {
        $platformId = $this->resolvePlatformId($request);
        if (!$platformId) {
            return $this->noPlatformResponse();
        }

        try {
            $template = $this->templateService->getById($id, $platformId);
            $this->authorize('preview', $template);

            $result = $this->templateService->preview(
                $template,
                $request->input('context', []),
                $request->input('version') ? (int) $request->input('version') : null,
                $request->only(['subject', 'preheader', 'html_content', 'text_content', 'sms_content'])
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Catálogo mestre de variáveis disponíveis para templates.
     */
    public function variables(Request $request): JsonResponse
    {
        $channel = $request->query('channel');

        return response()->json([
            'success' => true,
            'data' => TemplateVariableRegistry::grouped($channel),
            'all' => TemplateVariableRegistry::all(),
        ], 200);
    }

    /**
     * Catálogo de categorias de templates suportadas.
     */
    public function categories(): JsonResponse
    {
        $categories = [
            ['key' => 'GENERAL', 'label' => 'Geral'],
            ['key' => 'MARKETING', 'label' => 'Marketing & Campanhas'],
            ['key' => 'TRANSACTIONAL', 'label' => 'Transacional'],
            ['key' => 'RETENTION', 'label' => 'Retenção & Reativação'],
            ['key' => 'PROMOTION', 'label' => 'Promoções & Bônus'],
            ['key' => 'WELCOME', 'label' => 'Boas-Vindas (Onboarding)'],
            ['key' => 'DEPOSIT', 'label' => 'Depósitos & Financeiro'],
            ['key' => 'BETTING', 'label' => 'Apostas & Jogos'],
            ['key' => 'WITHDRAWAL', 'label' => 'Saques'],
            ['key' => 'ACCOUNT', 'label' => 'Conta & Segurança'],
        ];

        return response()->json([
            'success' => true,
            'data' => $categories,
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
