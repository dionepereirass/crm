<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'platform_id' => $this->platform_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'channel' => $this->channel,
            'status' => $this->status,
            'category' => $this->category,
            'current_version_id' => $this->current_version_id,
            'current_version' => $this->whenLoaded('currentVersion', fn() => TemplateVersionResource::make($this->currentVersion)),
            'versions_count' => $this->whenCounted('versions'),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'updater' => $this->whenLoaded('updater', fn() => [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
                'email' => $this->updater->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
