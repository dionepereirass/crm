<?php

namespace App\Http\Requests\Tags;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');
        return $this->user()?->can('update', $tag) ?? false;
    }

    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('tags', 'name')
                    ->where('platform_id', $tag->platform_id)
                    ->ignore($tag->id),
            ],
            'color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
