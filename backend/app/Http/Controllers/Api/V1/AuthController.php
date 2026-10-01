<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::with(['roles.permissions', 'platforms'])
            ->where('email', $request->email)
            ->first();

        // Security: Generic message to prevent email enumeration
        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($request->throttleKey(), 60);

            Log::warning('[AUTH] Falha de autenticação (credenciais inválidas)', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Credenciais de acesso inválidas.',
                'errors' => [
                    'auth' => ['Credenciais de acesso inválidas.'],
                ],
            ], 401);
        }

        // Account Status Check
        if (!$user->isActive()) {
            Log::warning('[AUTH] Tentativa de login com usuário inativo/bloqueado', [
                'user_id' => $user->id,
                'status' => $user->status,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conta de usuário desativada ou bloqueada. Entre em contato com o administrador.',
                'errors' => [
                    'status' => ['Conta inativa ou bloqueada.'],
                ],
            ], 403);
        }

        // Platform association check
        if ($request->filled('platform_id')) {
            $platformId = $request->input('platform_id');
            if (!$user->hasAccessToPlatform($platformId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado: você não tem vínculo com a plataforma informada.',
                    'errors' => [
                        'platform' => ['Vínculo de plataforma inexistente.'],
                    ],
                ], 403);
            }
        } elseif (!$user->isSuperAdmin() && $user->platforms->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário sem nenhuma plataforma vinculada no sistema.',
                'errors' => [
                    'platform' => ['Nenhuma plataforma atribuída.'],
                ],
            ], 403);
        }

        // Clear brute-force rate limiter on success
        RateLimiter::clear($request->throttleKey());

        $deviceName = $request->device_name ?? 'web_session';
        $token = $user->createToken($deviceName)->plainTextToken;

        Log::info('[AUTH] Usuário autenticado com sucesso', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('slug')->all(),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Autenticado com sucesso.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], 200);
    }

    /**
     * Terminate current session and revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        Log::info('[AUTH] Logout realizado', [
            'user_id' => $user?->id,
            'email' => $user?->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso.',
            'data' => null,
        ], 200);
    }

    /**
     * Retrieve authenticated user profile with roles, permissions and platforms.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'platforms']);

        return response()->json([
            'success' => true,
            'message' => 'Dados do usuário autenticado.',
            'data' => new UserResource($user),
        ], 200);
    }

    /**
     * Revoke all issued access tokens for the authenticated user.
     */
    public function revoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();

        Log::info('[AUTH] Todos os tokens revogados', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Todos os tokens de acesso foram revogados.',
            'data' => null,
        ], 200);
    }
}
