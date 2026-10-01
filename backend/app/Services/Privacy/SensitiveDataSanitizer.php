<?php

namespace App\Services\Privacy;

class SensitiveDataSanitizer
{
    /**
     * Chaves cujos valores devem ser completamente redigidos/ocultados.
     */
    protected const REDACT_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'apikey',
        'key',
        'authorization',
        'access_token',
        'refresh_token',
        'bearer',
        'hmac',
        'signature',
        'private_key',
        'card_number',
        'cvv',
        'cvc',
        'security_code',
        'bank_account_number',
    ];

    /**
     * Sanitiza recursivamente um array, redigindo segredos e mascarando PII sensível.
     */
    public function sanitize(?array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // 1. Redação completa de segredos e credenciais
            if ($this->shouldRedactKey($lowerKey)) {
                $sanitized[$key] = '***REDACTED***';
                continue;
            }

            // 2. Se for array aninhado, sanitiza recursivamente
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            // 3. Mascaramento inteligente de campos conhecidos
            if (is_string($value)) {
                if (str_contains($lowerKey, 'email')) {
                    $sanitized[$key] = $this->maskEmail($value);
                    continue;
                }

                if (str_contains($lowerKey, 'phone') || str_contains($lowerKey, 'whatsapp')) {
                    $sanitized[$key] = $this->maskPhone($value);
                    continue;
                }

                if (str_contains($lowerKey, 'cpf') || str_contains($lowerKey, 'document')) {
                    $sanitized[$key] = $this->maskCpf($value);
                    continue;
                }
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Mascara e-mails: "dionisio@example.com" -> "di***@example.com"
     */
    public function maskEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        $parts = explode('@', trim($email));
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $visibleLen = min(2, mb_strlen($name));
        $prefix = mb_substr($name, 0, $visibleLen);

        return $prefix . '***@' . $domain;
    }

    /**
     * Mascara telefones: "5531999991234" -> "5531*****1234"
     */
    public function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);

        if ($len <= 4) {
            return '****';
        }

        $prefix = substr($digits, 0, min(4, $len - 4));
        $suffix = substr($digits, -4);

        return $prefix . '*****' . $suffix;
    }

    /**
     * Mascara CPF: "12345678900" -> "***.***.***-00" ou "***.456.789-**"
     */
    public function maskCpf(?string $cpf): ?string
    {
        if (empty($cpf)) {
            return null;
        }

        $clean = preg_replace('/\D/', '', $cpf);
        if (strlen($clean) !== 11) {
            return '***.***.***-**';
        }

        return '***.' . substr($clean, 3, 3) . '.' . substr($clean, 6, 3) . '-**';
    }

    /**
     * Mascara Cartão de Crédito: "1234567812345678" -> "**** **** **** 5678"
     */
    public function maskCreditCard(?string $card): ?string
    {
        if (empty($card)) {
            return null;
        }

        $clean = preg_replace('/\D/', '', $card);
        $len = strlen($clean);

        if ($len < 4) {
            return '****';
        }

        $last4 = substr($clean, -4);
        return '**** **** **** ' . $last4;
    }

    /**
     * Previne CSV Injection (Formula Injection / DDE).
     * Se uma célula textual iniciar com =, +, -, @, \t ou \r, prefixa com apóstrofo (').
     */
    public function sanitizeCsvCell(mixed $value): mixed
    {
        if (is_string($value)) {
            // Se iniciar com caracteres interpretados como fórmulas por planilhas
            if (preg_match('/^[=+\-@\t\r]/', $value)) {
                return "'" . $value;
            }
        }

        return $value;
    }

    /**
     * Sanitiza uma linha inteira de dados tabulares para exportação segura em CSV.
     */
    public function sanitizeCsvRow(array $row): array
    {
        return array_map(fn($v) => $this->sanitizeCsvCell($v), $row);
    }

    protected function shouldRedactKey(string $key): bool
    {
        foreach (self::REDACT_KEYS as $redactKey) {
            if (str_contains($key, $redactKey)) {
                return true;
            }
        }
        return false;
    }
}
