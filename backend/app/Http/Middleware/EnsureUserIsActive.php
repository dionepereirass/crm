<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isActive()) {
            // Revoke current token if using Sanctum
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Sua conta de usuário está desativada ou bloqueada. Entre em contato com o suporte.',
                'errors' => [
                    'auth' => ['Usuário inativo ou bloqueado.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
