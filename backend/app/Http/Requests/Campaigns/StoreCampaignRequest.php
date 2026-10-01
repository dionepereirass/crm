<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('campaigns.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'channel' => ['required', 'string', 'in:EMAIL,SMS,email,sms'],
            'segment_id' => ['required', 'integer'],
            'template_id' => ['required', 'integer'],
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

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'channel.required' => 'O canal de disparo (EMAIL ou SMS) é obrigatório.',
            'segment_id.required' => 'O segmento de público é obrigatório.',
            'template_id.required' => 'O template de comunicação é obrigatório.',
        ];
    }
}
