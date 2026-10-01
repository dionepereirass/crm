<?php

namespace App\Services\Players;

use App\DTOs\Players\CreatePlayerDTO;
use App\DTOs\Players\PlayerFilterDTO;
use App\DTOs\Players\UpdatePlayerDTO;
use App\Models\Player;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlayerService
{
    /**
     * List players with filters, sorting, and pagination (offset or cursor).
     */
    public function list(PlayerFilterDTO $filters): LengthAwarePaginator|CursorPaginator
    {
        $query = Player::with(['tags', 'platform']);

        // Search in name, email, phone or external_id
        if (!empty($filters->search)) {
            $term = '%' . trim($filters->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('external_id', 'like', $term);
            });
        }

        // Exact filters
        if (!empty($filters->status)) {
            $query->where('status', $filters->status);
        }

        if (!empty($filters->state)) {
            $query->where('state', strtoupper($filters->state));
        }

        if (!empty($filters->city)) {
            $query->where('city', 'like', '%' . $filters->city . '%');
        }

        if (!empty($filters->affiliate)) {
            $query->where('affiliate', $filters->affiliate);
        }

        if (!empty($filters->tagId)) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('tags.id', $filters->tagId);
            });
        }

        // Date ranges
        if (!empty($filters->createdFrom)) {
            $query->whereDate('created_at', '>=', $filters->createdFrom);
        }

        if (!empty($filters->createdTo)) {
            $query->whereDate('created_at', '<=', $filters->createdTo);
        }

        if (!empty($filters->lastLoginFrom)) {
            $query->whereDate('last_login_at', '>=', $filters->lastLoginFrom);
        }

        if (!empty($filters->lastLoginTo)) {
            $query->whereDate('last_login_at', '<=', $filters->lastLoginTo);
        }

        // Sorting
        $query->orderBy($filters->sort, $filters->direction);

        // Pagination Strategy
        if ($filters->useCursor) {
            return $query->cursorPaginate($filters->perPage);
        }

        return $query->paginate($filters->perPage);
    }

    /**
     * Create a new player record.
     */
    public function create(CreatePlayerDTO $dto): Player
    {
        return DB::transaction(function () use ($dto) {
            $player = Player::create([
                'platform_id' => $dto->platformId,
                'external_id' => $dto->externalId,
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'whatsapp' => $dto->whatsapp,
                'cpf' => $dto->cpf,
                'birth_date' => $dto->birthDate,
                'gender' => $dto->gender,
                'city' => $dto->city,
                'state' => $dto->state ? strtoupper($dto->state) : null,
                'zip_code' => $dto->zipCode,
                'status' => $dto->status,
                'source' => $dto->source ?? 'manual_crm',
                'affiliate' => $dto->affiliate,
                'promo_code' => $dto->promoCode,
                'custom_fields' => $dto->customFields,
            ]);

            if (!empty($dto->tagIds)) {
                $player->tags()->syncWithoutDetaching($dto->tagIds);
            }

            return $player->load(['tags', 'platform']);
        });
    }

    /**
     * Update an existing player.
     */
    public function update(Player $player, UpdatePlayerDTO $dto): Player
    {
        return DB::transaction(function () use ($player, $dto) {
            $attributes = array_filter([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'whatsapp' => $dto->whatsapp,
                'cpf' => $dto->cpf,
                'birth_date' => $dto->birthDate,
                'gender' => $dto->gender,
                'city' => $dto->city,
                'state' => $dto->state ? strtoupper($dto->state) : null,
                'zip_code' => $dto->zipCode,
                'status' => $dto->status,
                'source' => $dto->source,
                'affiliate' => $dto->affiliate,
                'promo_code' => $dto->promoCode,
            ], fn($val) => $val !== null);

            if ($dto->customFields !== null) {
                $currentFields = $player->custom_fields ?? [];
                $attributes['custom_fields'] = array_merge($currentFields, $dto->customFields);
            }

            $player->update($attributes);

            if ($dto->tagIds !== null) {
                $player->tags()->sync($dto->tagIds);
            }

            return $player->fresh(['tags', 'platform']);
        });
    }

    /**
     * Soft delete player.
     */
    public function delete(Player $player): void
    {
        $player->delete();
    }

    /**
     * Attach a tag to a player with cross-platform validation.
     */
    public function attachTag(Player $player, Tag $tag): void
    {
        if ($player->platform_id !== $tag->platform_id) {
            throw new InvalidArgumentException('Não é permitido associar tags pertencentes a outra plataforma.');
        }

        $player->tags()->syncWithoutDetaching([$tag->id]);
    }

    /**
     * Detach a tag from a player.
     */
    public function detachTag(Player $player, Tag $tag): void
    {
        $player->tags()->detach($tag->id);
    }
}
