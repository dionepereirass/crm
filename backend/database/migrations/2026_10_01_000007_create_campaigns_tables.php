<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Campanhas de Marketing
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('channel', 20); // EMAIL, SMS
            $table->string('status', 30)->default('DRAFT'); // DRAFT, VALIDATING, READY, SCHEDULED, PROCESSING, PAUSED, COMPLETED, CANCELLED, FAILED
            $table->foreignId('segment_id')->constrained('segments')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained('template_versions')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            
            // Metadados de remetente
            $table->string('from_name', 150)->nullable();
            $table->string('from_email', 190)->nullable();
            $table->string('reply_to', 190)->nullable();
            $table->string('sms_sender', 50)->nullable();

            // Métricas e contadores
            $table->integer('audience_count')->default(0);
            $table->integer('eligible_count')->default(0);
            $table->integer('messages_created')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->integer('messages_failed')->default(0);

            // Marcos temporais
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'scheduled_at']);
            $table->index(['platform_id', 'channel']);
            $table->index(['platform_id', 'created_at']);
        });

        // 2. Coluna campaign_id na tabela messages
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'campaign_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('campaign_id')->nullable()->after('platform_id')->constrained('campaigns')->nullOnDelete();
                $table->index(['platform_id', 'campaign_id']);
            });
        }

        // 3. Tabela de Destinatários da Campanha (Snapshot de Audiência)
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('channel', 20); // EMAIL, SMS
            $table->string('recipient', 190);
            $table->string('status', 30)->default('PENDING'); // PENDING, SKIPPED, QUEUED, SENT, FAILED, CANCELLED
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('reason', 100)->nullable(); // MARKETING_CONSENT_REQUIRED, INVALID_CONTACT, PLAYER_BLOCKED, etc.
            $table->timestamps();

            // Deduplicação estrita: um jogador não pode aparecer mais de uma vez na mesma campanha e canal
            $table->unique(['campaign_id', 'player_id', 'channel'], 'unique_campaign_player_channel');

            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'player_id']);
            $table->index(['platform_id', 'player_id']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'campaign_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropForeign(['campaign_id']);
                $table->dropColumn('campaign_id');
            });
        }

        Schema::dropIfExists('campaigns');
    }
};
