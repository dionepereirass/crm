<?php

namespace App\Http\Requests\Providers;

use App\Models\Provider;
use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Provider::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', 'string', 'in:EMAIL,SMS,email,sms'],
            'driver' => ['required', 'string', 'in:fake_email,fake_sms,brevo,zenvia'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,ERROR,active,inactive,error'],
            'is_default' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'is_fallback' => ['nullable', 'boolean'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
            'configuration' => ['nullable', 'array'],
            'api_key' => ['nullable', 'string'],
            'api_token' => ['nullable', 'string'],
            'credentials' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do provedor é obrigatório.',
            'channel.required' => 'O canal (EMAIL ou SMS) é obrigatório.',
            'driver.required' => 'O driver do provedor é obrigatório.',
            'driver.in' => 'O driver especificado não é suportado.',
        ];
    }
}
