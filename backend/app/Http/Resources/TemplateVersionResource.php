<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'template_id' => $this->template_id,
            'version' => (int) $this->version,
            'status' => $this->status,
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'html_content' => $this->html_content,
            'text_content' => $this->text_content,
            'sms_content' => $this->sms_content,
            'variables_schema' => $this->variables_schema ?? [],
            'metadata' => $this->metadata ?? [],
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
