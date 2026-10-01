<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'platform_id' => $this->platform_id,
            'event_type' => [
                'id' => $this->eventType?->id,
                'key' => $this->eventType?->key,
                'name' => $this->eventType?->name,
            ],
            'external_event_id' => $this->external_event_id,
            'player' => $this->player ? [
                'id' => $this->player->id,
                'external_id' => $this->player->external_id,
                'name' => $this->player->name,
                'email' => $this->player->masked_email,
            ] : null,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'payload' => $this->payload,
            'normalized_payload' => $this->normalized_payload,
            'processing_status' => $this->processing_status?->value ?? $this->processing_status,
            'attempts' => $this->attempts,
            'error_message' => $this->error_message,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
