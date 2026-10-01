<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'campaign_id' => $this->campaign_id,
            'player_id' => $this->player_id,
            'channel' => $this->channel,
            'recipient' => $this->masked_recipient,
            'status' => $this->status,
            'message_id' => $this->message_id,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'player' => $this->whenLoaded('player', function () {
                return $this->player ? [
                    'id' => $this->player->id,
                    'name' => $this->player->name,
                    'status' => $this->player->status,
                ] : null;
            }),
        ];
    }
}
