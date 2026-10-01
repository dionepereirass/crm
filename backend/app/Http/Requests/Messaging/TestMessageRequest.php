<?php

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

class TestMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('test', Message::class) ?? false;
    }

    public function rules(): array
    {
        $channel = strtoupper($this->input('channel', 'EMAIL'));

        return [
            'channel' => ['required', 'string', 'in:EMAIL,SMS,email,sms'],
            'provider_id' => ['nullable', 'integer'],
            'recipient' => ['required', 'string', 'max:190'],
            'subject' => [$channel === 'EMAIL' ? 'required' : 'nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'O canal (EMAIL ou SMS) é obrigatório.',
            'recipient.required' => 'O destinatário da mensagem é obrigatório.',
            'subject.required' => 'O assunto é obrigatório para testes de E-mail.',
            'content.required' => 'O conteúdo da mensagem de teste é obrigatório.',
        ];
    }
}
