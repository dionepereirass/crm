<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request and check required permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado.',
                'errors' => ['auth' => ['Token ausente ou inválido.']],
            ], 401);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado: permissão insuficiente para executar esta ação.',
                'errors' => ['permission' => ["Requer a permissão '{$permission}'."]],
            ], 403);
        }

        return $next($request);
    }
}
