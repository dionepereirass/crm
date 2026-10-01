<?php

namespace App\Http\Requests\Templates;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Template::class) ?? false;
    }

    public function rules(): array
    {
        $channel = strtoupper($this->input('channel', ''));

        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'channel' => ['required', 'string', 'in:EMAIL,SMS,email,sms'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:DRAFT,ACTIVE,ARCHIVED,draft,active,archived'],
            'subject' => [$channel === 'EMAIL' ? 'required' : 'nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'html_content' => [$channel === 'EMAIL' ? 'required' : 'nullable', 'string'],
            'text_content' => ['nullable', 'string'],
            'sms_content' => [$channel === 'SMS' ? 'required' : 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do template é obrigatório.',
            'channel.required' => 'O canal (EMAIL ou SMS) é obrigatório.',
            'subject.required' => 'O assunto do e-mail é obrigatório para templates de E-mail.',
            'html_content.required' => 'O conteúdo HTML é obrigatório para templates de E-mail.',
            'sms_content.required' => 'O texto da mensagem é obrigatório para templates de SMS.',
        ];
    }
}
