<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tracking\MessageEventService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    public function __construct(
        protected MessageEventService $eventService
    ) {}

    /**
     * Rastreia a abertura de e-mails retornando um pixel 1x1 transparente (GIF).
     */
    public function open(Request $request, string $token): Response
    {
        // 1x1 Transparent GIF base64
        $pixelBinary = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        $this->eventService->recordOpen($token, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response($pixelBinary, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Length' => strlen($pixelBinary),
        ]);
    }

    /**
     * Rastreia cliques em hiperlinks e redireciona com segurança contra Open Redirect.
     */
    public function click(Request $request, string $token)
    {
        $result = $this->eventService->recordClick($token, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $destinationUrl = $result['destination_url'];

        // Proteção contra Open Redirect e links inexistentes
        if (empty($destinationUrl) || !filter_var($destinationUrl, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Link inválido ou não encontrado.'], 404);
        }

        // Validação estrita de esquema (apenas HTTP ou HTTPS)
        $scheme = strtolower((string) parse_url($destinationUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return response()->json(['error' => 'Destino inválido ou inseguro.'], 400);
        }

        return redirect()->away($destinationUrl, 302, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Processa a solicitação de descadastramento (Unsubscribe / LGPD) e exibe tela amigável de confirmação.
     */
    public function unsubscribe(Request $request, string $token): Response
    {
        $result = $this->eventService->recordUnsubscribe($token, [
            'ip' => $request->ip(),
        ]);

        $statusColor = $result['success'] ? '#10b981' : '#ef4444';
        $title = $result['success'] ? 'Descadastrado com Sucesso' : 'Aviso de Descadastramento';
        $message = htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} — BET CRM</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 32px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: rgba(16, 185, 129, 0.1);
            color: {$statusColor};
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .footer {
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #334155;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <div class="footer">
            Conformidade LGPD • BET CRM
        </div>
    </div>
</body>
</html>
HTML;

        return response($html, $result['success'] ? 200 : 400, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
