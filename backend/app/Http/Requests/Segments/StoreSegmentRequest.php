<?php

namespace App\Http\Requests\Segments;

use App\Models\Segment;
use Illuminate\Foundation\Http\FormRequest;

class StoreSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Segment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:DRAFT,ACTIVE,INACTIVE,draft,active,inactive'],
            'rules_tree' => ['required', 'array'],
            'rules_tree.operator' => ['required', 'string', 'in:AND,OR,and,or'],
            'rules_tree.children' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do segmento é obrigatório.',
            'rules_tree.required' => 'A estrutura de regras (rules_tree) é obrigatória.',
            'rules_tree.operator.required' => 'O operador raiz do grupo de regras (AND/OR) é obrigatório.',
        ];
    }
}
