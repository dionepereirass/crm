<?php

namespace App\Http\Requests\Tags;

use App\Services\Platforms\PlatformContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Tag::class) ?? false;
    }

    public function rules(): array
    {
        $platformContext = app(PlatformContext::class);
        $platformId = $this->input('platform_id') ?? $platformContext->getPlatformId();

        return [
            'platform_id' => ['nullable', 'exists:platforms,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tags', 'name')->where(function ($query) use ($platformId) {
                    return $query->where('platform_id', $platformId);
                }),
            ],
            'color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
