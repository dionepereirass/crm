<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Providers\ProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckController extends Controller
{
    public function __construct(
        protected ProviderManager $providerManager
    ) {}

    /**
     * Overall system health check.
     */
    public function index(): JsonResponse
    {
        $db = $this->checkDatabase();
        $redis = $this->checkRedis();
        $queue = $this->checkQueue();
        $providers = $this->checkProvidersData();

        $allHealthy = ($db['status'] === 'ok') &&
                      ($redis['status'] === 'ok') &&
                      ($queue['status'] === 'ok');

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name', 'BET CRM'),
                'env' => config('app.env'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'checks' => [
                'database' => $db,
                'redis' => $redis,
                'queue' => $queue,
                'providers' => $providers,
            ],
        ], $statusCode);
    }

    /**
     * Database specific health check.
     */
    public function database(): JsonResponse
    {
        $result = $this->checkDatabase();
        $status = ($result['status'] === 'ok') ? 200 : 503;
        return response()->json($result, $status);
    }

    /**
     * Redis specific health check.
     */
    public function redis(): JsonResponse
    {
        $result = $this->checkRedis();
        $status = ($result['status'] === 'ok') ? 200 : 503;
        return response()->json($result, $status);
    }

    /**
     * Queue specific health check.
     */
    public function queue(): JsonResponse
    {
        $result = $this->checkQueue();
        $status = ($result['status'] === 'ok') ? 200 : 503;
        return response()->json($result, $status);
    }

    /**
     * Providers specific health check.
     */
    public function providers(): JsonResponse
    {
        return response()->json($this->checkProvidersData());
    }

    // ------------------------------------------------------------------------
    // Internal Check Helpers
    // ------------------------------------------------------------------------

    protected function sanitizeErrorMessage(string $message): string
    {
        return mb_convert_encoding($message, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    protected function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
                'database' => DB::connection()->getDatabaseName(),
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'connection' => config('database.default'),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    protected function checkRedis(): array
    {
        $start = microtime(true);

        if (app()->environment('testing') && config('cache.default') === 'array') {
            return [
                'status' => 'ok',
                'client' => 'mock_testing',
                'response' => 'PONG (testing simulated)',
                'latency_ms' => 0.5,
            ];
        }

        try {
            $pong = Redis::connection()->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'client' => config('database.redis.client'),
                'response' => (string) $pong,
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'client' => config('database.redis.client'),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    protected function checkQueue(): array
    {
        $start = microtime(true);
        try {
            $driver = config('queue.default');
            $size = ($driver === 'sync') ? 0 : Queue::size();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'driver' => $driver,
                'pending_jobs' => $size,
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'driver' => config('queue.default'),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    protected function checkProvidersData(): array
    {
        return [
            'mode' => config('services.providers.mock_enabled', true) ? 'mock' : 'live',
            'drivers' => $this->providerManager->checkAllHealth(),
        ];
    }
}
