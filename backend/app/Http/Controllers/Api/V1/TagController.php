<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\Platforms\PlatformContext;
use App\Services\Players\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(
        protected TagService $tagService,
        protected PlatformContext $platformContext
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $tags = $this->tagService->list();

        return response()->json([
            'success' => true,
            'message' => 'Tags recuperadas com sucesso.',
            'data' => TagResource::collection($tags),
        ], 200);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $platformId = $request->input('platform_id') ?? $this->platformContext->getPlatformId();

        if (!$platformId) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma plataforma identificada para criar a tag.',
                'errors' => ['platform_id' => ['Plataforma obrigatória.']],
            ], 422);
        }

        $tag = $this->tagService->create($request->validated(), (int) $platformId);

        return response()->json([
            'success' => true,
            'message' => "Tag '{$tag->name}' criada com sucesso.",
            'data' => new TagResource($tag),
        ], 201);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        $updated = $this->tagService->update($tag, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tag atualizada com sucesso.',
            'data' => new TagResource($updated),
        ], 200);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $this->tagService->delete($tag);

        return response()->json([
            'success' => true,
            'message' => 'Tag excluída com sucesso.',
            'data' => null,
        ], 200);
    }
}
