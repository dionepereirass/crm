<?php

namespace App\Services\Webhooks;

use App\Models\Platform;

class WebhookSecurityService
{
    /**
     * Valida a assinatura HMAC-SHA256 recebida no header contra o webhook_secret da plataforma.
     */
    public function validateSignature(string $rawBody, ?string $providedSignature, Platform $platform): bool
    {
        if (empty($providedSignature) || empty($platform->webhook_secret)) {
            return false;
        }

        // Remove prefixo comum "sha256=" se enviado
        $cleanSignature = preg_replace('/^sha256=/i', '', trim($providedSignature));

        // Computa a assinatura esperada com o RAW BODY original
        $expectedSignature = hash_hmac('sha256', $rawBody, $platform->webhook_secret);

        return hash_equals(strtolower($expectedSignature), strtolower($cleanSignature));
    }

    /**
     * Computa a assinatura HMAC-SHA256 para payloads (útil para testes e documentação).
     */
    public function computeSignature(string $rawBody, string $webhookSecret): string
    {
        return hash_hmac('sha256', $rawBody, $webhookSecret);
    }

    /**
     * Mascara cabeçalhos sensíveis para armazenamento seguro em logs de auditoria.
     */
    public function maskSensitiveHeaders(array $headers): array
    {
        $sensitiveKeys = [
            'authorization',
            'x-webhook-signature',
            'cookie',
            'set-cookie',
            'x-api-key',
            'api-key',
            'secret',
            'token',
        ];

        $masked = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys)) {
                $masked[$key] = ['*** MASKED ***'];
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
