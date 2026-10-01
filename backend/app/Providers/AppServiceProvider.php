<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Policies\CampaignPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Platforms\PlatformContext::class, function () {
            return new \App\Services\Platforms\PlatformContext();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Campaign::class, CampaignPolicy::class);
        \Illuminate\Support\Facades\RateLimiter::for('webhooks', function (\Illuminate\Http\Request $request) {
            $platformSlug = (string) $request->route('platform_slug', 'default');
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(300)->by($platformSlug)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de requisições excedido para esta plataforma. Tente novamente mais tarde.',
                ], 429);
            });
        });

        \Illuminate\Support\Facades\RateLimiter::for('exports', function (\Illuminate\Http\Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($key)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de exportações excedido (máximo 10 por minuto). Aguarde antes de solicitar novos downloads.',
                ], 429);
            });
        });

        \Illuminate\Support\Facades\RateLimiter::for('messages_test', function (\Illuminate\Http\Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($key)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de disparos de teste atingido. Tente novamente em breve.',
                ], 429);
            });
        });

        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(600)->by($key);
        });
    }
}
