<?php

namespace App\Http\Requests\Segments;

use Illuminate\Foundation\Http\FormRequest;

class PreviewSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules_tree' => ['required', 'array'],
            'rules_tree.operator' => ['required', 'string', 'in:AND,OR,and,or'],
            'rules_tree.children' => ['required', 'array'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
