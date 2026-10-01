<?php

namespace App\Services\Templates;

class TemplateRenderer
{
    public function __construct(
        protected TemplateSanitizer $sanitizer
    ) {}

    /**
     * Renderiza um template de e-mail completo com substituição segura de variáveis.
     */
    public function renderEmail(
        string $subject,
        ?string $preheader,
        ?string $htmlContent,
        ?string $textContent,
        array $context = []
    ): array {
        $renderedSubject = $this->renderString($subject, $context);
        $renderedPreheader = $preheader ? $this->renderString($preheader, $context) : null;
        $renderedHtml = $htmlContent ? $this->renderString($htmlContent, $context) : '';
        $renderedText = $textContent ? $this->renderString($textContent, $context) : strip_tags($renderedHtml);

        return [
            'subject' => $renderedSubject,
            'preheader' => $renderedPreheader,
            'html' => $this->sanitizer->sanitizeHtml($renderedHtml),
            'text' => $this->sanitizer->sanitizeText($renderedText),
        ];
    }

    /**
     * Renderiza conteúdo de SMS e calcula métricas de caracteres/segmentos.
     */
    public function renderSms(
        string $smsContent,
        array $context = []
    ): array {
        $renderedSms = $this->renderString($smsContent, $context);
        $cleanSms = $this->sanitizer->sanitizeText($renderedSms);
        $metrics = $this->calculateSmsMetrics($cleanSms);

        return [
            'sms' => $cleanSms,
            'metrics' => $metrics,
        ];
    }

    /**
     * Substitui com segurança as variáveis no formato {{chave}} ou {{chave|default:"fallback"}}.
     */
    public function renderString(?string $content, array $context = []): string
    {
        if (empty($content)) {
            return '';
        }

        $flatContext = $this->flattenContext($context);

        $pattern = '/\{\{\s*([a-zA-Z0-9_\.]+)(?:\s*\|\s*default\s*:\s*["\']([^"\']*)["\'])?\s*\}\}/';

        return preg_replace_callback($pattern, function ($matches) use ($flatContext) {
            $key = trim($matches[1]);
            $fallback = $matches[2] ?? '';

            if (array_key_exists($key, $flatContext) && $flatContext[$key] !== null && $flatContext[$key] !== '') {
                return (string) $flatContext[$key];
            }

            // Tratamento especial para player.first_name derivado de player.name
            if ($key === 'player.first_name' && !empty($flatContext['player.name'])) {
                $parts = explode(' ', trim((string) $flatContext['player.name']));
                return $parts[0];
            }

            return $fallback;
        }, $content);
    }

    /**
     * Calcula métricas GSM-7 vs Unicode e estimativa de segmentos SMS.
     */
    public function calculateSmsMetrics(string $text): array
    {
        $charCount = mb_strlen($text, 'UTF-8');
        if ($charCount === 0) {
            return [
                'char_count' => 0,
                'encoding' => 'GSM-7',
                'segments' => 0,
                'limit_per_segment' => 160,
                'exceeded' => false,
            ];
        }

        // Tabela de caracteres padrão GSM 7-bit (0x00 - 0x7F com extensões)
        // Se houver qualquer caractere fora da tabela GSM-7, o SMS é enviado como Unicode (UCS-2)
        $isGsm7 = $this->isGsm7Compatible($text);

        if ($isGsm7) {
            $encoding = 'GSM-7';
            if ($charCount <= 160) {
                $segments = 1;
                $limitPerSegment = 160;
            } else {
                // SMS concatenado GSM-7 usa 153 caracteres por segmento (7 bytes para UDH)
                $segments = (int) ceil($charCount / 153);
                $limitPerSegment = 153;
            }
        } else {
            $encoding = 'UNICODE';
            if ($charCount <= 70) {
                $segments = 1;
                $limitPerSegment = 70;
            } else {
                // SMS concatenado Unicode usa 67 caracteres por segmento
                $segments = (int) ceil($charCount / 67);
                $limitPerSegment = 67;
            }
        }

        return [
            'char_count' => $charCount,
            'encoding' => $encoding,
            'segments' => $segments,
            'limit_per_segment' => $limitPerSegment,
            'exceeded' => $segments > 1,
        ];
    }

    /**
     * Verifica compatibilidade estrita com charset GSM-7.
     */
    protected function isGsm7Compatible(string $text): bool
    {
        // Caracteres básicos GSM 7-bit e extensão
        $gsmBasic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $gsmExtended = "|^€{}[~]\\";

        $allowed = $gsmBasic . $gsmExtended;

        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            if (mb_strpos($allowed, $char, 0, 'UTF-8') === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Achata o contexto multidimensional para chaves no formato dot.notation.
     * Ex: ['player' => ['name' => 'Carlos', 'deposit' => ['total' => 100]]] ->
     * ['player.name' => 'Carlos', 'player.deposit.total' => 'R$ 100,00']
     */
    protected function flattenContext(array $context, string $prefix = ''): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenContext($value, $fullKey));
            } else {
                $formatted = $this->formatVariableValue($fullKey, $value);
                $result[$fullKey] = $formatted;
            }
        }

        return $result;
    }

    /**
     * Formata valores para apresentação humana amigável.
     */
    protected function formatVariableValue(string $key, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        // Formatação monetária para totais e valores financeiros
        if (
            str_contains($key, '.total') ||
            str_contains($key, '.last_amount') ||
            str_contains($key, '.avg_amount') ||
            str_contains($key, '.amount')
        ) {
            if (is_numeric($value)) {
                return 'R$ ' . number_format((float) $value, 2, ',', '.');
            }
        }

        return (string) $value;
    }
}
