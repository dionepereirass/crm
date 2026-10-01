<?php

namespace App\Services\Providers\Contracts;

use App\DTOs\Providers\MessagePayload;
use App\DTOs\Providers\ProviderHealthResult;
use App\DTOs\Providers\ProviderResult;

interface MessageProviderInterface
{
    /**
     * Envia uma mensagem através do provedor.
     */
    public function send(MessagePayload $message): ProviderResult;

    /**
     * Valida configuração e testa conectividade com o endpoint do provedor (Health Check).
     */
    public function validateConfiguration(): ProviderHealthResult;

    /**
     * Retorna o nome amigável do provedor.
     */
    public function getName(): string;

    /**
     * Retorna o canal suportado ('EMAIL' ou 'SMS').
     */
    public function getChannel(): string;

    /**
     * Retorna o identificador do driver ('fake_email', 'fake_sms', 'brevo', 'zenvia').
     */
    public function getDriver(): string;
}
