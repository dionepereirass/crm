<?php

namespace App\Http\Middleware;

use App\Models\Platform;
use App\Services\Platforms\PlatformContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantPlatformContext
{
    public function __construct(
        protected PlatformContext $platformContext
    ) {}

    /**
     * Handle an incoming request and set/validate platform context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $platformHeader = $request->header('X-Platform-Id')
            ?? $request->input('platform_id')
            ?? $request->header('X-Platform-Slug');

        if ($platformHeader) {
            $cacheKey = is_numeric($platformHeader)
                ? "betcrm:platform:id:{$platformHeader}"
                : "betcrm:platform:slug:{$platformHeader}";

            $platform = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($platformHeader) {
                return is_numeric($platformHeader)
                    ? Platform::find($platformHeader)
                    : Platform::where('slug', $platformHeader)->first();
            });

            if (!$platform) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plataforma solicitada não foi encontrada.',
                    'errors' => ['platform' => ['Plataforma inexistente.']],
                ], 404);
            }

            // Verify user access
            if ($user && !$user->hasAccessToPlatform($platform)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado: você não tem permissão para operar nesta plataforma.',
                    'errors' => ['platform' => ['Isolamento multi-tenant: acesso não autorizado.']],
                ], 403);
            }

            $this->platformContext->setPlatform($platform);
        } elseif ($user) {
            // If user has exactly one platform, automatically set it
            if (!$user->isSuperAdmin() && $user->platforms->count() === 1) {
                $this->platformContext->setPlatform($user->platforms->first());
            }
        }

        return $next($request);
    }
}
