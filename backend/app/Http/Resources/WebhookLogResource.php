<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'platform_id' => $this->platform_id,
            'event_id' => $this->event_id,
            'external_event_id' => $this->external_event_id,
            'endpoint' => $this->endpoint,
            'signature_valid' => $this->signature_valid,
            'processing_status' => $this->processing_status?->value ?? $this->processing_status,
            'http_status' => $this->http_status,
            'payload' => $this->payload,
            'headers' => $this->headers,
            'error_message' => $this->error_message,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'received_at' => $this->received_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
