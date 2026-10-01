<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona índices compostos e de alta seletividade para acelerar consultas multi-tenant de alto volume.
     */
    public function up(): void
    {
        // 1. Mensagens: Acelera filtros por status e data no dashboard e listagens
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['platform_id', 'status', 'created_at'], 'idx_messages_platform_status_created');
            $table->index(['campaign_id', 'status'], 'idx_messages_campaign_status');
        });

        // 2. Eventos de Mensagem: Acelera contagens de aberturas únicas, cliques e métricas de campanhas
        Schema::table('message_events', function (Blueprint $table) {
            $table->index(['message_id', 'event_type'], 'idx_message_events_message_type');
            $table->index(['event_type', 'created_at'], 'idx_message_events_type_created');
        });

        // 3. Eventos Transacionais: Acelera agregações analíticas (depósitos, saques, apostas) por período
        Schema::table('events', function (Blueprint $table) {
            $table->index(['platform_id', 'event_type_id', 'occurred_at'], 'idx_events_platform_type_occurred');
        });

        // 4. Jogadores: Acelera análises de coorte, retenção e segmentações ativas
        Schema::table('players', function (Blueprint $table) {
            $table->index(['platform_id', 'status', 'created_at'], 'idx_players_platform_status_created');
        });

        // 5. Destinatários de Campanha: Acelera processamento assíncrono em lotes (chunking)
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->index(['campaign_id', 'status', 'id'], 'idx_campaign_recipients_chunking');
        });

        // 6. Execuções de Automação: Acelera monitoramento de jornadas ativas e histórico
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->index(['platform_id', 'status', 'created_at'], 'idx_auto_runs_platform_status_created');
            $table->index(['automation_id', 'status'], 'idx_auto_runs_auto_status');
        });

        // 7. Auditoria de Privacidade e LGPD: Acelera trilhas de auditoria por período e por entidade
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['platform_id', 'action', 'created_at'], 'idx_audit_logs_platform_action_created');
            $table->index(['platform_id', 'resource_type', 'created_at'], 'idx_audit_logs_platform_resource_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_platform_action_created');
            $table->dropIndex('idx_audit_logs_platform_resource_created');
        });

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropIndex('idx_auto_runs_platform_status_created');
            $table->dropIndex('idx_auto_runs_auto_status');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex('idx_campaign_recipients_chunking');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('idx_players_platform_status_created');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_platform_type_occurred');
        });

        Schema::table('message_events', function (Blueprint $table) {
            $table->dropIndex('idx_message_events_message_type');
            $table->dropIndex('idx_message_events_type_created');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_platform_status_created');
            $table->dropIndex('idx_messages_campaign_status');
        });
    }
};
