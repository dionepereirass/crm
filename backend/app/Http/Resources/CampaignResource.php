<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'platform_id' => $this->platform_id,
            'name' => $this->name,
            'description' => $this->description,
            'channel' => $this->channel,
            'status' => $this->status,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to' => $this->reply_to,
            'sms_sender' => $this->sms_sender,
            'audience_count' => $this->audience_count,
            'eligible_count' => $this->eligible_count,
            'messages_created' => $this->messages_created,
            'messages_sent' => $this->messages_sent,
            'messages_failed' => $this->messages_failed,
            'progress_percentage' => $this->progress_percentage,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'segment' => $this->whenLoaded('segment', function () {
                return [
                    'id' => $this->segment->id,
                    'name' => $this->segment->name,
                    'slug' => $this->segment->slug,
                    'status' => $this->segment->status,
                ];
            }),
            'template' => $this->whenLoaded('template', function () {
                return [
                    'id' => $this->template->id,
                    'name' => $this->template->name,
                    'channel' => $this->template->channel,
                    'status' => $this->template->status,
                ];
            }),
            'template_version' => $this->whenLoaded('templateVersion', function () {
                return [
                    'id' => $this->templateVersion->id,
                    'version' => $this->templateVersion->version,
                    'status' => $this->templateVersion->status,
                    'subject' => $this->templateVersion->subject,
                ];
            }),
            'provider' => $this->whenLoaded('provider', function () {
                return $this->provider ? [
                    'id' => $this->provider->id,
                    'name' => $this->provider->name,
                    'driver' => $this->provider->driver,
                    'channel' => $this->provider->channel,
                ] : null;
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ] : null;
            }),
            'updater' => $this->whenLoaded('updater', function () {
                return $this->updater ? [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ] : null;
            }),
        ];
    }
}
