<?php

namespace App\Http\Requests\Segments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorize logic in controller via policy
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:DRAFT,ACTIVE,INACTIVE,draft,active,inactive'],
            'rules_tree' => ['sometimes', 'required', 'array'],
            'rules_tree.operator' => ['required_with:rules_tree', 'string', 'in:AND,OR,and,or'],
            'rules_tree.children' => ['required_with:rules_tree', 'array'],
        ];
    }
}
