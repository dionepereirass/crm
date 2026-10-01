<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Player;
use App\Models\User;
use App\Services\Analytics\AnalyticsCacheService;
use App\Services\Segments\SegmentQueryCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceAndScalabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platform;
    protected User $adminUser;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platform = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminUser->platforms()->syncWithoutDetaching([$this->platform->id]);
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;
    }

    /**
     * Teste 1: Valida a criação dos novos índices de performance e escalabilidade no banco.
     */
    public function test_performance_compound_indexes_exist_in_database(): void
    {
        // Verifica se as tabelas e índices estão presentes
        $this->assertTrue(Schema::hasTable('messages'));
        $this->assertTrue(Schema::hasTable('message_events'));
        $this->assertTrue(Schema::hasTable('events'));
        $this->assertTrue(Schema::hasTable('players'));
        $this->assertTrue(Schema::hasTable('campaign_recipients'));
        $this->assertTrue(Schema::hasTable('automation_runs'));
        $this->assertTrue(Schema::hasTable('audit_logs'));

        // Em Laravel 11/12, Schema::getIndexes() retorna os índices da tabela
        $messageIndexes = collect(Schema::getIndexes('messages'))->pluck('name')->all();
        $this->assertContains('idx_messages_platform_status_created', $messageIndexes);
        $this->assertContains('idx_messages_campaign_status', $messageIndexes);

        $eventIndexes = collect(Schema::getIndexes('events'))->pluck('name')->all();
        $this->assertContains('idx_events_platform_type_occurred', $eventIndexes);

        $playerIndexes = collect(Schema::getIndexes('players'))->pluck('name')->all();
        $this->assertContains('idx_players_platform_status_created', $playerIndexes);
    }

    /**
     * Teste 2: Cache de TenantPlatformContext evita consultas repetidas ao banco para a mesma plataforma.
     */
    public function test_tenant_platform_context_caches_platform_lookup(): void
    {
        Cache::flush();

        // 1ª requisição: preenche o cache
        $response1 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/players');
        $response1->assertStatus(200);

        // Verifica se a chave no cache foi gravada
        $cacheKey = "betcrm:platform:id:{$this->platform->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // 2ª requisição: utiliza o cache
        $response2 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/players');
        $response2->assertStatus(200);
    }

    /**
     * Teste 3: Alterações no modelo Platform invalidam o cache automaticamente.
     */
    public function test_updating_platform_invalidates_cached_context(): void
    {
        $cacheKey = "betcrm:platform:id:{$this->platform->id}";
        Cache::put($cacheKey, $this->platform, 3600);
        $this->assertTrue(Cache::has($cacheKey));

        // Atualiza a plataforma
        $this->platform->update(['name' => 'Bet Brasil Atualizada']);

        // O cache deve ter sido limpo pelo model hook
        $this->assertFalse(Cache::has($cacheKey));
    }

    /**
     * Teste 4: AnalyticsCacheService armazena resultados e evita reprocessamento de métricas pesadas.
     */
    public function test_analytics_cache_service_remembers_and_invalidates_metrics(): void
    {
        /** @var AnalyticsCacheService $cacheService */
        $cacheService = app(AnalyticsCacheService::class);

        $executionCount = 0;
        $computation = function () use (&$executionCount) {
            $executionCount++;
            return ['active_players' => 1500, 'total_turnover' => 50000.00];
        };

        // 1ª chamada: executa o callback
        $result1 = $cacheService->remember($this->platform->id, 'financial_kpis', ['period' => '30d'], 300, $computation);
        $this->assertEquals(1, $executionCount);
        $this->assertEquals(1500, $result1['active_players']);

        // 2ª chamada com mesmos filtros: retorna do cache sem re-executar
        $result2 = $cacheService->remember($this->platform->id, 'financial_kpis', ['period' => '30d'], 300, $computation);
        $this->assertEquals(1, $executionCount, "O callback não deveria ser executado novamente");
        $this->assertEquals(1500, $result2['active_players']);

        // Invalida a métrica específica
        $cacheKey = $cacheService->buildCacheKey($this->platform->id, 'financial_kpis', ['period' => '30d']);
        Cache::forget($cacheKey);

        // 3ª chamada: re-executa após invalidação
        $result3 = $cacheService->remember($this->platform->id, 'financial_kpis', ['period' => '30d'], 300, $computation);
        $this->assertEquals(2, $executionCount);
    }

    /**
     * Teste 5: SegmentQueryCompiler suporta chunkById com grande volume de dados mantendo baixo uso de memória.
     */
    public function test_segment_query_compiler_supports_chunk_by_id_efficiently(): void
    {
        // Cria 50 jogadores para teste de paginação/chunking
        $playersData = [];
        for ($i = 1; $i <= 50; $i++) {
            $playersData[] = [
                'platform_id' => $this->platform->id,
                'external_id' => "PLY-PERF-{$i}",
                'name' => "Jogador Perf {$i}",
                'email' => "perf{$i}@example.com",
                'phone' => "55119999900{$i}",
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Player::insert($playersData);

        /** @var SegmentQueryCompiler $compiler */
        $compiler = app(SegmentQueryCompiler::class);

        $rulesTree = [
            'operator' => 'AND',
            'children' => [
                [
                    'field' => 'player.status',
                    'operator' => 'equals',
                    'value' => 'ACTIVE',
                ],
            ],
        ];

        $builder = $compiler->compile($rulesTree, $this->platform->id);

        $processedCount = 0;
        $chunkIterations = 0;

        // Processa em lotes de 10
        $builder->chunkById(10, function ($chunk) use (&$processedCount, &$chunkIterations) {
            $chunkIterations++;
            $processedCount += $chunk->count();
        });

        $this->assertGreaterThanOrEqual(5, $chunkIterations);
        $this->assertGreaterThanOrEqual(50, $processedCount);
    }

    /**
     * Teste 6: Configuração do Laravel Horizon contempla todas as filas especializadas de alta prioridade e batch.
     */
    public function test_horizon_configuration_covers_all_system_queues(): void
    {
        $configuredQueues = config('horizon.defaults.supervisor-1.queue');

        $requiredQueues = [
            'webhooks',
            'events',
            'messages',
            'emails',
            'sms',
            'campaigns',
            'campaign_messages',
            'automations',
            'analytics',
            'reports',
            'default',
        ];

        foreach ($requiredQueues as $queue) {
            $this->assertContains(
                $queue,
                $configuredQueues,
                "A fila '{$queue}' deve estar configurada no supervisor do Horizon"
            );
        }

        // Verifica limites de memória e timeout adequados
        $this->assertGreaterThanOrEqual(128, config('horizon.defaults.supervisor-1.memory'));
        $this->assertGreaterThanOrEqual(90, config('horizon.defaults.supervisor-1.timeout'));
    }
}
