<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modificações na tabela messages (Tracking tokens e vínculo direto com Player)
        Schema::table('messages', function (Blueprint $table) {
            $table->string('open_tracking_token', 64)->nullable()->unique()->after('idempotency_key');
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('open_tracking_token');
            $table->foreignId('player_id')->nullable()->after('campaign_id')->constrained('players')->nullOnDelete();
            $table->timestamp('opened_at')->nullable()->after('failed_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');

            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'created_at']);
            $table->index('player_id');
        });

        // 2. Modificações na tabela message_events (Provider foreign key e índices otimizados)
        Schema::table('message_events', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->after('message_id')->constrained('providers')->nullOnDelete();
            
            $table->index('occurred_at');
            $table->index(['provider_id', 'provider_event_id']);
        });

        // 3. Tabela de Links Rastreáveis (Click Tracking de Mensagens)
        Schema::create('message_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('tracking_token', 64)->unique();
            $table->text('destination_url');
            $table->integer('clicks_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
        });

        // 4. Tabela de Métricas Agregadas das Campanhas (Snapshot de Performance & Cache em Banco)
        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->integer('audience_count')->default(0);
            $table->integer('eligible_count')->default(0);
            $table->integer('queued_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('unique_openers_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('unique_clickers_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->decimal('delivery_rate', 5, 2)->default(0.00);
            $table->decimal('open_rate', 5, 2)->default(0.00);
            $table->decimal('click_rate', 5, 2)->default(0.00);
            $table->decimal('bounce_rate', 5, 2)->default(0.00);
            $table->decimal('failure_rate', 5, 2)->default(0.00);
            $table->decimal('unsubscribe_rate', 5, 2)->default(0.00);
            $table->timestamps();

            $table->index(['platform_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('message_links');

        Schema::table('message_events', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'provider_event_id']);
            $table->dropIndex(['occurred_at']);
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'status']);
            $table->dropIndex(['campaign_id', 'created_at']);
            $table->dropIndex(['player_id']);
            $table->dropForeign(['player_id']);
            $table->dropColumn([
                'open_tracking_token',
                'unsubscribe_token',
                'player_id',
                'opened_at',
                'clicked_at',
            ]);
        });
    }
};
