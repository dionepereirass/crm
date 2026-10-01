<?php

namespace App\Services\Players;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function list(): Collection
    {
        return Tag::withCount('players')->orderBy('name', 'asc')->get();
    }

    public function create(array $data, int $platformId): Tag
    {
        return Tag::create([
            'platform_id' => $platformId,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? '#10b981',
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->update(array_filter([
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'color' => $data['color'] ?? null,
            'description' => $data['description'] ?? null,
        ], fn($v) => $v !== null));

        return $tag->fresh();
    }

    public function delete(Tag $tag): void
    {
        $tag->delete();
    }
}
