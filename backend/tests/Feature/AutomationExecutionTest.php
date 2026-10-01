<?php

namespace Tests\Feature;

use App\Enums\AutomationNodeType;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationStepStatus;
use App\Enums\AutomationTriggerType;
use App\Jobs\ProcessAutomationStepJob;
use App\Jobs\ResumeAutomationRunJob;
use App\Jobs\StartAutomationRunJob;
use App\Models\Automation;
use App\Models\AutomationEdge;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\Consent;
use App\Models\Message;
use App\Models\Platform;
use App\Models\Player;
use App\Models\Provider;
use App\Models\Tag;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Automations\AutomationTriggerService;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomationExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platform;
    protected Player $player;
    protected Provider $fakeEmailProvider;
    protected Provider $fakeSmsProvider;
    protected Template $emailTemplate;
    protected TemplateVersion $emailVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platform = Platform::where('slug', 'bet-brasil')->firstOrFail();

        $this->player = Player::create([
            'platform_id' => $this->platform->id,
            'external_id' => 'player_auto_001',
            'name' => 'Carlos Silva',
            'email' => 'carlos.auto@example.com',
            'phone' => '5511999990001',
            'status' => 'ACTIVE',
        ]);

        $this->fakeEmailProvider = Provider::where('platform_id', $this->platform->id)
            ->where('channel', 'EMAIL')
            ->first() ?? Provider::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $this->platform->id,
                'name' => 'Fake Email Engine',
                'channel' => 'EMAIL',
                'driver' => 'fake_email',
                'is_active' => true,
                'priority' => 1,
            ]);

        $this->emailTemplate = Template::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->platform->id,
            'name' => 'Template Boas-vindas Auto',
            'slug' => 'template-boas-vindas-auto',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $this->emailVersion = TemplateVersion::create([
            'template_id' => $this->emailTemplate->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Olá {{first_name}}, bem-vindo!',
            'html_content' => '<p>Olá {{name}}, sua conta foi criada com sucesso.</p>',
            'text_content' => 'Olá {{name}}, sua conta foi criada.',
            'published_at' => now(),
        ]);
    }

    public function test_full_linear_journey_execution_with_tag_action(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Boas-vindas Linear',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::PLAYER_CREATED,
        ]);

        $triggerNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'trigger-1',
            'node_type' => AutomationNodeType::TRIGGER,
            'name' => 'Gatilho Cadastro',
            'configuration' => ['trigger_type' => 'PLAYER_CREATED'],
        ]);

        $tagNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'action-tag',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Adicionar Tag Novo Jogador',
            'configuration' => [
                'action_type' => 'ADD_TAG',
                'tag_name' => 'Novo Jogador',
            ],
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $triggerNode->id,
            'target_node_id' => $tagNode->id,
        ]);

        Queue::fake([StartAutomationRunJob::class]);

        // Dispara o gatilho
        $triggerService = app(AutomationTriggerService::class);
        $dispatched = $triggerService->dispatchForTrigger(
            $this->platform->id,
            $this->player->id,
            AutomationTriggerType::PLAYER_CREATED->value,
            'event-test-101'
        );

        $this->assertEquals(1, $dispatched);
        Queue::assertPushed(StartAutomationRunJob::class);

        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertEquals(AutomationRunStatus::RUNNING, $run->status);

        // Executa o início da jornada
        app()->call([new StartAutomationRunJob($run->id), 'handle']);

        // Processa nó de gatilho
        app()->call([new ProcessAutomationStepJob($run->id, $triggerNode->id), 'handle']);

        // Processa nó de ação
        app()->call([new ProcessAutomationStepJob($run->id, $tagNode->id), 'handle']);

        // Valida que o jogador recebeu a tag
        $this->assertTrue($this->player->tags()->where('name', 'Novo Jogador')->exists());

        // Valida que a execução foi concluída
        $run->refresh();
        $this->assertEquals(AutomationRunStatus::COMPLETED, $run->status);
        $this->assertNotNull($run->completed_at);
        $this->assertEquals(2, $run->steps()->count());
    }

    public function test_condition_branching_true_and_false(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Ramificação por Status',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::LOGIN,
        ]);

        $triggerNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'trigger-1',
            'node_type' => AutomationNodeType::TRIGGER,
            'name' => 'Gatilho Login',
        ]);

        $conditionNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'cond-1',
            'node_type' => AutomationNodeType::CONDITION,
            'name' => 'Jogador Ativo?',
            'configuration' => [
                'field' => 'player.status',
                'operator' => '=',
                'value' => 'ACTIVE',
            ],
        ]);

        $tagActive = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'tag-active',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Tag Ativo',
            'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'Jogador Ativo'],
        ]);

        $tagInactive = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'tag-inactive',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Tag Inativo',
            'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'Jogador Inativo'],
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $triggerNode->id,
            'target_node_id' => $conditionNode->id,
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $conditionNode->id,
            'target_node_id' => $tagActive->id,
            'condition_key' => 'true',
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $conditionNode->id,
            'target_node_id' => $tagInactive->id,
            'condition_key' => 'false',
        ]);

        // Dispara e executa
        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'status' => AutomationRunStatus::RUNNING,
            'started_at' => now(),
        ]);

        app()->call([new ProcessAutomationStepJob($run->id, $conditionNode->id), 'handle']);

        // Como player->status == 'ACTIVE', o próximo nó a ser chamado pelo job seria tag-active
        $step = $run->steps()->where('node_id', $conditionNode->id)->first();
        $this->assertEquals(AutomationStepStatus::COMPLETED, $step->status);
        $this->assertTrue($step->output['result']);

        // Executa ramo verdadeiro
        app()->call([new ProcessAutomationStepJob($run->id, $tagActive->id), 'handle']);

        $this->assertTrue($this->player->tags()->where('name', 'Jogador Ativo')->exists());
        $this->assertFalse($this->player->tags()->where('name', 'Jogador Inativo')->exists());
    }

    public function test_wait_node_schedules_delayed_job_and_resumes(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Jornada com Espera',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::DEPOSIT_SUCCESS,
        ]);

        $waitNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'wait-step',
            'node_type' => AutomationNodeType::WAIT,
            'name' => 'Aguardar 24 Horas',
            'configuration' => ['amount' => 24, 'unit' => 'hours'],
        ]);

        $afterWaitTag = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'tag-after',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Tag Pós Espera',
            'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'Retido 24h'],
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $waitNode->id,
            'target_node_id' => $afterWaitTag->id,
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'status' => AutomationRunStatus::RUNNING,
            'started_at' => now(),
        ]);

        Queue::fake([ResumeAutomationRunJob::class]);

        // Processa o nó de WAIT
        app()->call([new ProcessAutomationStepJob($run->id, $waitNode->id), 'handle']);

        $run->refresh();
        $this->assertEquals(AutomationRunStatus::WAITING, $run->status);

        $waitStep = $run->steps()->where('node_id', $waitNode->id)->firstOrFail();
        $this->assertEquals(AutomationStepStatus::WAITING, $waitStep->status);
        $this->assertEquals(86400, $waitStep->output['delay_seconds']);

        // Simula retomada da espera pelo ResumeAutomationRunJob
        app()->call([new ResumeAutomationRunJob($run->id, $waitNode->id), 'handle']);

        $waitStep->refresh();
        $this->assertEquals(AutomationStepStatus::COMPLETED, $waitStep->status);

        // Processa o nó seguinte
        app()->call([new ProcessAutomationStepJob($run->id, $afterWaitTag->id), 'handle']);

        $run->refresh();
        $this->assertEquals(AutomationRunStatus::COMPLETED, $run->status);
        $this->assertTrue($this->player->tags()->where('name', 'Retido 24h')->exists());
    }

    public function test_send_email_action_enforces_lgpd_consent(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Disparo com Consentimento',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::PLAYER_CREATED,
        ]);

        $emailNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'send-email-1',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Enviar E-mail Boas-vindas',
            'configuration' => [
                'action_type' => 'SEND_EMAIL',
                'template_id' => $this->emailTemplate->id,
                'provider_id' => $this->fakeEmailProvider->id,
            ],
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'status' => AutomationRunStatus::RUNNING,
            'started_at' => now(),
        ]);

        // 1. Cenário SEM consentimento: deve pular (SKIPPED)
        $this->assertFalse($this->player->hasMarketingConsent('EMAIL'));

        app()->call([new ProcessAutomationStepJob($run->id, $emailNode->id), 'handle']);

        $stepWithoutConsent = $run->steps()->latest('id')->first();
        $this->assertEquals(AutomationStepStatus::SKIPPED, $stepWithoutConsent->status);
        $this->assertEquals('MARKETING_CONSENT_MISSING', $stepWithoutConsent->output['reason']);
        $this->assertEquals(0, Message::where('player_id', $this->player->id)->count());

        // 2. Cenário COM consentimento: deve enviar
        Consent::create([
            'player_id' => $this->player->id,
            'channel' => 'EMAIL',
            'is_granted' => true,
            'consent_source' => 'REGISTRATION_FORM',
            'granted_at' => now(),
        ]);

        $this->player->refresh();
        $this->assertTrue($this->player->hasMarketingConsent('EMAIL'));

        $run2 = AutomationRun::create([
            'automation_id' => $automation->id,
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'status' => AutomationRunStatus::RUNNING,
            'started_at' => now(),
        ]);

        app()->call([new ProcessAutomationStepJob($run2->id, $emailNode->id), 'handle']);

        $stepWithConsent = $run2->steps()->latest('id')->first();
        $this->assertEquals(AutomationStepStatus::COMPLETED, $stepWithConsent->status);
        $this->assertNotNull($stepWithConsent->output['message_id']);

        $this->assertDatabaseHas('messages', [
            'id' => $stepWithConsent->output['message_id'],
            'player_id' => $this->player->id,
            'template_id' => $this->emailTemplate->id,
        ]);
    }

    public function test_idempotency_prevents_duplicate_runs_for_same_event(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Idempotência Estrita',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::DEPOSIT_SUCCESS,
        ]);

        AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'trigger-1',
            'node_type' => AutomationNodeType::TRIGGER,
            'name' => 'Gatilho Depósito',
        ]);

        $triggerService = app(AutomationTriggerService::class);

        // Primeiro disparo
        $dispatchedFirst = $triggerService->dispatchForTrigger(
            $this->platform->id,
            $this->player->id,
            'DEPOSIT_SUCCESS',
            'dep-unique-12345'
        );
        $this->assertEquals(1, $dispatchedFirst);

        // Segundo disparo com o MESMO event_id: deve ser descartado por idempotência
        $dispatchedSecond = $triggerService->dispatchForTrigger(
            $this->platform->id,
            $this->player->id,
            'DEPOSIT_SUCCESS',
            'dep-unique-12345'
        );
        $this->assertEquals(0, $dispatchedSecond);

        $this->assertEquals(1, AutomationRun::where('automation_id', $automation->id)->count());
    }

    public function test_reentry_blocking_policy(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Bloqueio de Reentrada',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::BET_PLACED,
            'settings' => [
                'reentry_policy' => 'BLOCK_REENTRY',
            ],
        ]);

        AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'trigger-1',
            'node_type' => AutomationNodeType::TRIGGER,
            'name' => 'Gatilho Aposta',
        ]);

        Queue::fake([StartAutomationRunJob::class]);

        $triggerService = app(AutomationTriggerService::class);

        // 1. Inicia primeiro run (fica RUNNING)
        $dispatched1 = $triggerService->dispatchForTrigger(
            $this->platform->id,
            $this->player->id,
            'BET_PLACED',
            'bet-001'
        );
        $this->assertEquals(1, $dispatched1);

        // 2. Novo evento diferente enquanto o primeiro está RUNNING -> deve ser bloqueado
        $dispatched2 = $triggerService->dispatchForTrigger(
            $this->platform->id,
            $this->player->id,
            'BET_PLACED',
            'bet-002'
        );
        $this->assertEquals(0, $dispatched2);

        // 3. Finaliza a primeira execução
        $firstRun = AutomationRun::where('automation_id', $automation->id)->first();
        $firstRun->update(['status' => AutomationRunStatus::COMPLETED]);

        // 4. Agora novo evento é permitido pois não há mais run ativo
        $dispatched3 = $triggerService->dispatchForTrigger(
            $this->platform->id,
            $this->player->id,
            'BET_PLACED',
            'bet-003'
        );
        $this->assertEquals(1, $dispatched3);
    }

    public function test_run_cancellation_stops_execution(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platform->id,
            'name' => 'Cancelamento de Execução',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::DEPOSIT_SUCCESS,
        ]);

        $waitNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'wait-step',
            'node_type' => AutomationNodeType::WAIT,
            'name' => 'Aguardar',
            'configuration' => ['amount' => 1, 'unit' => 'hours'],
        ]);

        $tagNode = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'tag-step',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Tag Final',
            'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'Nao Deve Adicionar'],
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $waitNode->id,
            'target_node_id' => $tagNode->id,
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'status' => AutomationRunStatus::WAITING,
            'started_at' => now(),
        ]);

        // Cancela a execução
        $run->update([
            'status' => AutomationRunStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        // Tentativa de retomar a espera não deve avançar a jornada
        app()->call([new ResumeAutomationRunJob($run->id, $waitNode->id), 'handle']);

        $run->refresh();
        $this->assertEquals(AutomationRunStatus::CANCELLED, $run->status);
        $this->assertFalse($this->player->tags()->where('name', 'Nao Deve Adicionar')->exists());
    }
}
