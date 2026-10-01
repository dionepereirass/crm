<?php

namespace App\Services\Tracking;

use Illuminate\Support\Str;

class TrackingProcessor
{
    /**
     * Processa o conteúdo HTML de um e-mail:
     * 1. Injeta o pixel 1x1 transparente de abertura.
     * 2. Reescreve hiperlinks para tracking com tokens opacos seguros.
     * 3. Processa e injeta link de unsubscribe (descadastramento LGPD).
     *
     * @param string $html Conteúdo HTML original renderizado
     * @param string $openToken Token opaco de abertura
     * @param string $unsubscribeToken Token opaco de descadastramento
     * @param string $baseUrl URL base da aplicação/API
     * @return array{html: string, links: array<int, array{tracking_token: string, destination_url: string}>}
     */
    public function process(
        string $html,
        string $openToken,
        string $unsubscribeToken,
        string $baseUrl
    ): array {
        $baseUrl = rtrim($baseUrl, '/');
        $processedHtml = $html;
        $links = [];

        // 1. Substitui tags explícitas de unsubscribe se existirem
        $unsubscribeUrl = "{$baseUrl}/api/v1/tracking/unsubscribe/{$unsubscribeToken}";
        $hasUnsubscribePlaceholder = false;

        $unsubscribePlaceholders = ['{{unsubscribe_url}}', '{{unsubscribe}}', '{{optout_url}}', '{unsubscribe}'];
        foreach ($unsubscribePlaceholders as $placeholder) {
            if (stripos($processedHtml, $placeholder) !== false) {
                $processedHtml = str_ireplace($placeholder, $unsubscribeUrl, $processedHtml);
                $hasUnsubscribePlaceholder = true;
            }
        }

        // 2. Transforma hiperlinks (<a href="...">) em links rastreáveis
        $pattern = '/<a\b([^>]*?)href=(["\'])(.*?)\2([^>]*?)>/is';
        
        $processedHtml = preg_replace_callback($pattern, function ($matches) use (&$links, $baseUrl, $unsubscribeUrl) {
            $beforeHref = $matches[1];
            $quote = $matches[2];
            $url = trim($matches[3]);
            $afterHref = $matches[4];

            // Ignora links especiais, âncoras, esquemas não-web ou links já rastreados
            if (
                empty($url) ||
                str_starts_with($url, '#') ||
                str_starts_with($url, 'mailto:') ||
                str_starts_with($url, 'tel:') ||
                str_starts_with($url, 'javascript:') ||
                str_starts_with($url, 'data:') ||
                str_contains($url, '/api/v1/tracking/') ||
                $url === $unsubscribeUrl
            ) {
                return $matches[0];
            }

            // Valida protocolo web estrito (apenas http e https permitidos contra SSRF e Open Redirect)
            if (!preg_match('/^https?:\/\//i', $url)) {
                return $matches[0];
            }

            // Gera token opaco e armazena destino
            $trackingToken = Str::random(48);
            $links[] = [
                'tracking_token' => $trackingToken,
                'destination_url' => $url,
            ];

            $trackingUrl = "{$baseUrl}/api/v1/tracking/click/{$trackingToken}";

            return "<a{$beforeHref}href={$quote}{$trackingUrl}{$quote}{$afterHref}>";
        }, $processedHtml);

        // 3. Se não houver placeholder explícito de unsubscribe no template, anexa rodapé padrão
        if (!$hasUnsubscribePlaceholder) {
            $footerHtml = "\n" . '<div style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#6b7280;font-family:sans-serif;"><p>Caso não deseje mais receber nossas mensagens, <a href="' . $unsubscribeUrl . '" style="color:#6b7280;text-decoration:underline;">cancele sua inscrição aqui</a>.</p></div>' . "\n";

            if (stripos($processedHtml, '</body>') !== false) {
                $processedHtml = preg_replace('/<\/body>/i', $footerHtml . '</body>', $processedHtml, 1);
            } else {
                $processedHtml .= $footerHtml;
            }
        }

        // 4. Injeta Pixel de Tracking de Abertura (1x1 transparente)
        $pixelUrl = "{$baseUrl}/api/v1/tracking/open/{$openToken}";
        $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none!important;max-height:0;max-width:0;opacity:0;overflow:hidden;" />';

        if (stripos($processedHtml, '</body>') !== false) {
            $processedHtml = preg_replace('/<\/body>/i', $pixelTag . "\n</body>", $processedHtml, 1);
        } else {
            $processedHtml .= "\n" . $pixelTag;
        }

        return [
            'html' => $processedHtml,
            'links' => $links,
        ];
    }
}
