<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class CreateTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'html_content' => ['nullable', 'string'],
            'text_content' => ['nullable', 'string'],
            'sms_content' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:DRAFT,PUBLISHED,draft,published'],
        ];
    }
}
