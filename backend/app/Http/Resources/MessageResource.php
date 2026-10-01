<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'platform_id' => $this->platform_id,
            'provider_id' => $this->provider_id,
            'channel' => $this->channel,
            'recipient' => $this->masked_recipient,
            'recipient_name' => $this->recipient_name,
            'subject' => $this->subject,
            'template_id' => $this->template_id,
            'template_version_id' => $this->template_version_id,
            'status' => $this->status,
            'provider_message_id' => $this->provider_message_id,
            'idempotency_key' => $this->idempotency_key,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'provider' => $this->whenLoaded('provider', function () {
                return [
                    'id' => $this->provider?->id,
                    'name' => $this->provider?->name,
                    'channel' => $this->provider?->channel,
                    'driver' => $this->provider?->driver,
                ];
            }),
            'template' => $this->whenLoaded('template', function () {
                return [
                    'id' => $this->template?->id,
                    'name' => $this->template?->name,
                ];
            }),
            'events_count' => $this->events()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
