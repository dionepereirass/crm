<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_overall_health_check_endpoint(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'app' => [
                    'name',
                    'env',
                    'php_version',
                    'laravel_version',
                ],
                'checks' => [
                    'database',
                    'redis',
                    'queue',
                    'providers',
                ],
            ]);
    }

    public function test_api_v1_versioned_health_check_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'healthy');
    }

    public function test_database_health_check_endpoint(): void
    {
        $response = $this->getJson('/health/database');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'connection',
                'database',
                'latency_ms',
            ])
            ->assertJsonPath('status', 'ok');
    }

    public function test_redis_health_check_endpoint(): void
    {
        $response = $this->getJson('/health/redis');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'client',
                'latency_ms',
            ])
            ->assertJsonPath('status', 'ok');
    }

    public function test_queue_health_check_endpoint(): void
    {
        $response = $this->getJson('/health/queue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'driver',
                'pending_jobs',
                'latency_ms',
            ])
            ->assertJsonPath('status', 'ok');
    }

    public function test_providers_health_check_endpoint(): void
    {
        $response = $this->getJson('/health/providers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mode',
                'drivers',
            ])
            ->assertJsonPath('mode', 'mock');
    }
}
