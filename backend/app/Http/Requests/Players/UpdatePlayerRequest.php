<?php

namespace App\Http\Requests\Players;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');
        return $this->user()?->can('update', $player) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
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
}
