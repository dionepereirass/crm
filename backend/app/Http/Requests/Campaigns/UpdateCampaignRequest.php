<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('campaigns.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'channel' => ['nullable', 'string', 'in:EMAIL,SMS,email,sms'],
            'segment_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'template_version_id' => ['nullable', 'integer'],
            'provider_id' => ['nullable', 'integer'],
            'from_name' => ['nullable', 'string', 'max:150'],
            'from_email' => ['nullable', 'email', 'max:190'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'sms_sender' => ['nullable', 'string', 'max:50'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
