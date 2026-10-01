<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabela Principal de Automações
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('DRAFT'); // DRAFT, ACTIVE, PAUSED, INACTIVE
            $table->string('trigger_type', 64); // PLAYER_CREATED, DEPOSIT_SUCCESS, etc.
            $table->jsonb('settings')->nullable(); // reentry_policy, cooldown_days, max_steps_per_run, etc.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'trigger_type']);
        });

        // 2. Nós do Grafo da Jornada (Nodes)
        Schema::create('automation_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->string('node_key', 64);
            $table->string('node_type', 32); // TRIGGER, CONDITION, ACTION, WAIT
            $table->string('name', 255);
            $table->jsonb('configuration')->nullable();
            $table->float('position_x')->default(0);
            $table->float('position_y')->default(0);
            $table->timestamps();

            $table->unique(['automation_id', 'node_key']);
            $table->index(['automation_id', 'node_type']);
        });

        // 3. Conexões / Arestas do Grafo (Edges)
        Schema::create('automation_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('source_node_id')->constrained('automation_nodes')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('automation_nodes')->cascadeOnDelete();
            $table->string('condition_key', 32)->nullable(); // 'true', 'false', 'default', null
            $table->timestamps();

            $table->index(['automation_id', 'source_node_id']);
            $table->index(['automation_id', 'target_node_id']);
        });

        // 4. Execuções da Automação (Runs)
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('status', 32)->default('RUNNING'); // RUNNING, WAITING, COMPLETED, FAILED, CANCELLED, SKIPPED
            $table->foreignId('current_node_id')->nullable()->constrained('automation_nodes')->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('retry_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'idempotency_key']);
            $table->index(['platform_id', 'status']);
            $table->index(['automation_id', 'player_id']);
            $table->index(['automation_id', 'status']);
        });

        // 5. Passos da Execução (Steps)
        Schema::create('automation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('automation_nodes')->cascadeOnDelete();
            $table->string('status', 32)->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED, SKIPPED, WAITING
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['automation_run_id', 'node_id']);
            $table->index(['automation_run_id', 'status']);
        });

        // 6. Trilha Técnica / Auditável da Automação (Logs)
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('automation_run_id')->nullable()->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignId('automation_step_id')->nullable()->constrained('automation_steps')->cascadeOnDelete();
            $table->string('level', 16)->default('INFO'); // INFO, WARNING, ERROR
            $table->string('event', 64);
            $table->text('message');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['automation_id', 'created_at']);
            $table->index(['automation_run_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_steps');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_edges');
        Schema::dropIfExists('automation_nodes');
        Schema::dropIfExists('automations');
    }
};
