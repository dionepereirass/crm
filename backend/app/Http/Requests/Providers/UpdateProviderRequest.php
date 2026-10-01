<?php

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('providers.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
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
}
