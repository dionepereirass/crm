<?php

namespace App\Http\Requests\Players;

use App\Services\Platforms\PlatformContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Player::class) ?? false;
    }

    public function rules(): array
    {
        $platformContext = app(PlatformContext::class);
        $platformId = $this->input('platform_id') ?? $platformContext->getPlatformId();

        return [
            'platform_id' => ['nullable', 'exists:platforms,id'],
            'external_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('players', 'external_id')->where(function ($query) use ($platformId) {
                    return $query->where('platform_id', $platformId);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:10'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,BLOCKED,PENDING,DELETED'],
            'source' => ['nullable', 'string', 'max:100'],
            'affiliate' => ['nullable', 'string', 'max:100'],
            'promo_code' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_id.unique' => 'Já existe um jogador com este ID Externo cadastrado nesta plataforma.',
            'external_id.required' => 'O ID Externo do jogador é obrigatório.',
            'name.required' => 'O nome do jogador é obrigatório.',
        ];
    }
}
