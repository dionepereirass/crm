<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'platform_id' => $this->platform_id,
            'name' => $this->name,
            'channel' => $this->channel,
            'driver' => $this->driver,
            'status' => $this->status,
            'is_default' => (bool) $this->is_default,
            'priority' => (int) $this->priority,
            'is_fallback' => (bool) $this->is_fallback,
            'rate_limit_per_minute' => (int) $this->rate_limit_per_minute,
            'configuration' => $this->configuration ?? [],
            'credentials_configured' => (bool) $this->credentials_configured,
            'messages_count' => $this->messages_count ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
