<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Provedores de Mensageria (Providers)
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('channel', 20); // 'EMAIL', 'SMS'
            $table->string('driver', 50); // 'fake_email', 'fake_sms', 'brevo', 'zenvia'
            $table->string('status', 20)->default('ACTIVE'); // 'ACTIVE', 'INACTIVE', 'ERROR'
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(1);
            $table->boolean('is_fallback')->default(false);
            $table->integer('rate_limit_per_minute')->default(60);
            $table->jsonb('configuration')->nullable(); // Configurações não-secretas (timeout, remetente, base_url)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform_id', 'channel', 'status']);
            $table->index(['platform_id', 'is_default']);
            $table->index(['platform_id', 'priority']);
        });

        // 2. Tabela de Credenciais Criptografadas dos Provedores (Separada para Segurança)
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained('providers')->cascadeOnDelete();
            $table->text('encrypted_credentials'); // Criptografado via Crypt::encryptString()
            $table->timestamps();
        });

        // 3. Tabela de Logs de Auditoria e Despacho de Provedores (Sem Secrets)
        Schema::create('provider_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('action', 50); // 'send', 'health_check', 'webhook'
            $table->string('status', 20); // 'SUCCESS', 'FAILED', 'RATE_LIMITED'
            $table->string('provider_message_id', 190)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->jsonb('request_metadata')->nullable(); // Metadados higienizados (sem API key, token, auth header)
            $table->jsonb('response_metadata')->nullable(); // Resposta sanitizada
            $table->timestamp('created_at')->useCurrent();

            $table->index(['platform_id', 'created_at']);
            $table->index(['provider_id', 'created_at']);
            $table->index('provider_message_id');
        });

        // 4. Tabela de Mensagens Transacionais / Individuais
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('channel', 20); // 'EMAIL', 'SMS'
            $table->string('recipient', 190);
            $table->string('recipient_name', 150)->nullable();
            $table->string('subject', 255)->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('template_versions')->nullOnDelete();
            $table->string('status', 20)->default('PENDING'); // PENDING, QUEUED, SENDING, SENT, FAILED, CANCELLED
            $table->string('provider_message_id', 190)->nullable();
            $table->string('idempotency_key', 190);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Garantia definitiva de idempotência por plataforma
            $table->unique(['platform_id', 'idempotency_key'], 'unique_platform_message_idempotency');
            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'channel', 'created_at']);
            $table->index('provider_message_id');
        });

        // 5. Tabela de Eventos de Ciclo de Vida da Mensagem
        Schema::create('message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('event_type', 50); // ACCEPTED, SENT, DELIVERED, BOUNCED, FAILED, REJECTED, CLICKED, OPENED, UNSUBSCRIBED
            $table->string('provider_event_id', 190)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['message_id', 'event_type']);
            $table->index('provider_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_events');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('provider_logs');
        Schema::dropIfExists('provider_credentials');
        Schema::dropIfExists('providers');
    }
};
