<?php

namespace App\Http\Requests\Tags;

use Illuminate\Foundation\Http\FormRequest;

class AttachTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');
        return $this->user()?->can('manageTags', $player) ?? false;
    }

    public function rules(): array
    {
        return [
            'tag_id' => ['required', 'integer', 'exists:tags,id'],
        ];
    }
}
