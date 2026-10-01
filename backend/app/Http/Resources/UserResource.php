<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'two_factor_enabled' => !empty($this->two_factor_confirmed_at),
            'roles' => $this->roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ]),
            'permissions' => $this->getAllPermissions()->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'group' => $p->group,
            ]),
            'platforms' => $this->isSuperAdmin()
                ? \App\Models\Platform::where('status', 'ACTIVE')->get(['id', 'uuid', 'name', 'slug', 'status'])
                : $this->platforms->map(fn($p) => [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'status' => $p->status,
                ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
