<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class PreviewTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer'],
            'context' => ['nullable', 'array'],
            'subject' => ['nullable', 'string'],
            'preheader' => ['nullable', 'string'],
            'html_content' => ['nullable', 'string'],
            'text_content' => ['nullable', 'string'],
            'sms_content' => ['nullable', 'string'],
        ];
    }
}
