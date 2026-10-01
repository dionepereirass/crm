<?php

namespace App\Services\Templates;

class TemplateSanitizer
{
    /**
     * Sanitiza conteúdo HTML para e-mails de forma segura,
     * bloqueando JavaScript, scripts maliciosos e iframes,
     * enquanto preserva estilos inline, tabelas e tags comuns de e-mail.
     */
    public function sanitizeHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Remove tags <script> e seu conteúdo
        $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // 2. Remove tags perigosas (iframe, object, embed, applet, form, base)
        $dangerousTags = ['iframe', 'object', 'embed', 'applet', 'form', 'base'];
        foreach ($dangerousTags as $tag) {
            $cleaned = preg_replace("/<{$tag}\b[^>]*>(.*?)<\/{$tag}>/is", '', $cleaned);
            $cleaned = preg_replace("/<{$tag}\b[^>]*\/?>/is", '', $cleaned);
        }

        // 3. Remove manipuladores de eventos inline (onload, onclick, onerror, onmouseover, etc.)
        $cleaned = preg_replace('/\s+on[a-zA-Z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $cleaned);

        // 4. Remove pseudoprotocolos javascript: e vbscript: em href, src, action
        $cleaned = preg_replace('/(href|src|action)\s*=\s*["\']\s*(javascript|vbscript|data):[^"\']*["\']/i', '$1="#"', $cleaned);
        $cleaned = preg_replace('/(href|src|action)\s*=\s*(javascript|vbscript|data):[^\s>]*/i', '$1="#"', $cleaned);

        // 5. Remove expression() em estilos inline do Internet Explorer
        $cleaned = preg_replace('/style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/i', '', $cleaned);

        return trim($cleaned);
    }

    /**
     * Sanitiza conteúdo de texto simples / SMS.
     */
    public function sanitizeText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Remove tags HTML se inseridas acidentalmente em canal SMS/Texto
        $clean = strip_tags($text);

        // Remove caracteres de controle perigosos (exceto quebras de linha e tabs comuns)
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);

        return trim($clean);
    }
}
