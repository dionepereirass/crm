<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Players\CreatePlayerDTO;
use App\DTOs\Players\PlayerFilterDTO;
use App\DTOs\Players\UpdatePlayerDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Players\StorePlayerRequest;
use App\Http\Requests\Players\UpdatePlayerRequest;
use App\Http\Requests\Tags\AttachTagRequest;
use App\Http\Resources\Player360Resource;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Models\Tag;
use App\Services\Platforms\PlatformContext;
use App\Services\Players\PlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PlayerController extends Controller
{
    public function __construct(
        protected PlayerService $playerService,
        protected PlatformContext $platformContext
    ) {}

    /**
     * List players with filters, search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Player::class);

        $filters = PlayerFilterDTO::fromRequest($request);
        $paginated = $this->playerService->list($filters);

        return response()->json([
            'success' => true,
            'message' => 'Jogadores recuperados com sucesso.',
            'data' => PlayerResource::collection($paginated),
            'pagination' => [
                'per_page' => $paginated->perPage(),
                'next_cursor' => method_exists($paginated, 'nextCursor') ? $paginated->nextCursor()?->encode() : null,
                'prev_cursor' => method_exists($paginated, 'previousCursor') ? $paginated->previousCursor()?->encode() : null,
                'total' => method_exists($paginated, 'total') ? $paginated->total() : null,
                'current_page' => method_exists($paginated, 'currentPage') ? $paginated->currentPage() : null,
                'last_page' => method_exists($paginated, 'lastPage') ? $paginated->lastPage() : null,
            ],
        ], 200);
    }

    /**
     * Store a new player.
     */
    public function store(StorePlayerRequest $request): JsonResponse
    {
        $platformId = $request->input('platform_id') ?? $this->platformContext->getPlatformId();

        if (!$platformId) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma plataforma identificada para associar o jogador.',
                'errors' => ['platform_id' => ['Plataforma obrigatória.']],
            ], 422);
        }

        $dto = new CreatePlayerDTO(
            platformId: (int) $platformId,
            externalId: $request->input('external_id'),
            name: $request->input('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            whatsapp: $request->input('whatsapp'),
            cpf: $request->input('cpf'),
            birthDate: $request->input('birth_date'),
            gender: $request->input('gender'),
            city: $request->input('city'),
            state: $request->input('state'),
            zipCode: $request->input('zip_code'),
            status: $request->input('status', 'ACTIVE'),
            source: $request->input('source', 'manual_crm'),
            affiliate: $request->input('affiliate'),
            promoCode: $request->input('promo_code'),
            customFields: $request->input('custom_fields', []),
            tagIds: $request->input('tag_ids', [])
        );

        $player = $this->playerService->create($dto);

        return response()->json([
            'success' => true,
            'message' => 'Jogador cadastrado com sucesso.',
            'data' => new PlayerResource($player),
        ], 201);
    }

    /**
     * Show player details.
     */
    public function show(Player $player): JsonResponse
    {
        $this->authorize('view', $player);

        $player->load(['tags', 'platform']);

        return response()->json([
            'success' => true,
            'message' => 'Dados do jogador recuperados.',
            'data' => new PlayerResource($player),
        ], 200);
    }

    /**
     * Consolidated 360 player profile.
     */
    public function show360(Player $player): JsonResponse
    {
        $this->authorize('view', $player);

        $player->load(['tags', 'platform', 'consents']);

        return response()->json([
            'success' => true,
            'message' => 'Ficha 360° do jogador recuperada.',
            'data' => new Player360Resource($player),
        ], 200);
    }

    /**
     * Update player.
     */
    public function update(UpdatePlayerRequest $request, Player $player): JsonResponse
    {
        $this->authorize('update', $player);

        $dto = new UpdatePlayerDTO(
            name: $request->input('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            whatsapp: $request->input('whatsapp'),
            cpf: $request->input('cpf'),
            birthDate: $request->input('birth_date'),
            gender: $request->input('gender'),
            city: $request->input('city'),
            state: $request->input('state'),
            zipCode: $request->input('zip_code'),
            status: $request->input('status'),
            source: $request->input('source'),
            affiliate: $request->input('affiliate'),
            promoCode: $request->input('promo_code'),
            customFields: $request->input('custom_fields'),
            tagIds: $request->input('tag_ids')
        );

        $updated = $this->playerService->update($player, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Dados do jogador atualizados com sucesso.',
            'data' => new PlayerResource($updated),
        ], 200);
    }

    /**
     * Delete player (soft delete).
     */
    public function destroy(Player $player): JsonResponse
    {
        $this->authorize('delete', $player);

        $this->playerService->delete($player);

        return response()->json([
            'success' => true,
            'message' => 'Jogador removido com sucesso.',
            'data' => null,
        ], 200);
    }

    /**
     * Attach a tag to a player.
     */
    public function attachTag(AttachTagRequest $request, Player $player): JsonResponse
    {
        $this->authorize('manageTags', $player);

        $tag = Tag::withoutGlobalScopes()->find($request->input('tag_id'));

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag não encontrada.',
                'errors' => ['tag_id' => ['Tag inexistente.']],
            ], 404);
        }

        try {
            $this->playerService->attachTag($player, $tag);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['tag' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Tag '{$tag->name}' associada ao jogador com sucesso.",
            'data' => new PlayerResource($player->fresh(['tags'])),
        ], 200);
    }

    /**
     * Detach a tag from a player.
     */
    public function detachTag(Player $player, Tag $tag): JsonResponse
    {
        $this->authorize('manageTags', $player);

        $this->playerService->detachTag($player, $tag);

        return response()->json([
            'success' => true,
            'message' => "Tag '{$tag->name}' removida do jogador.",
            'data' => new PlayerResource($player->fresh(['tags'])),
        ], 200);
    }
}
