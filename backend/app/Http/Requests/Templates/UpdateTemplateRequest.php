<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorize in controller via Policy
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:DRAFT,ACTIVE,ARCHIVED,draft,active,archived'],
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'html_content' => ['nullable', 'string'],
            'text_content' => ['nullable', 'string'],
            'sms_content' => ['nullable', 'string'],
        ];
    }
}
