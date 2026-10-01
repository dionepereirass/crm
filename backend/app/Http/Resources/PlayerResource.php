<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $canViewFullPii = $request->user()?->hasPermission('players.view') ?? false;
        $isDetailView = $request->routeIs('*.show', '*.update', '*.store');

        return [
            'id' => $this->id,
            'platform_id' => $this->platform_id,
            'platform' => $this->whenLoaded('platform', fn() => [
                'id' => $this->platform->id,
                'name' => $this->platform->name,
                'slug' => $this->platform->slug,
            ]),
            'external_id' => $this->external_id,
            'name' => $this->name,
            'email' => ($canViewFullPii && $isDetailView) ? $this->email : $this->masked_email,
            'phone' => ($canViewFullPii && $isDetailView) ? $this->phone : $this->masked_phone,
            'whatsapp' => ($canViewFullPii && $isDetailView) ? $this->whatsapp : $this->masked_phone,
            'cpf' => ($canViewFullPii && $isDetailView) ? $this->cpf : $this->masked_cpf,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'status' => $this->status,
            'source' => $this->source,
            'affiliate' => $this->affiliate,
            'promo_code' => $this->promo_code,
            'custom_fields' => $this->custom_fields ?? [],
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'registered_at' => $this->registered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
