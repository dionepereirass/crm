<?php

use App\Http\Controllers\Api\V1\HealthCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — BET CRM
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'name' => 'BET CRM Backend API',
        'status' => 'online',
        'version' => '1.0.0',
        'docs' => '/api/documentation',
        'health' => '/health',
    ]);
});

// Direct root health endpoints (as specified in Master Instruction)
Route::get('/health', [HealthCheckController::class, 'index'])->name('health');
Route::get('/health/database', [HealthCheckController::class, 'database'])->name('health.database');
Route::get('/health/redis', [HealthCheckController::class, 'redis'])->name('health.redis');
Route::get('/health/queue', [HealthCheckController::class, 'queue'])->name('health.queue');
Route::get('/health/providers', [HealthCheckController::class, 'providers'])->name('health.providers');
