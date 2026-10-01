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
        // 1. Event Types
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->onDelete('cascade');
            $table->string('key', 100);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['platform_id', 'key']);
        });

        // 2. Events Table (Normalizados)
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade');
            $table->foreignId('event_type_id')->constrained('event_types')->onDelete('restrict');
            $table->string('external_event_id', 255);
            $table->foreignId('player_id')->nullable()->constrained('players')->onDelete('set null');
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->json('normalized_payload')->nullable();
            $table->string('processing_status', 50)->default('RECEIVED');
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'external_event_id']);
            $table->index(['platform_id', 'processing_status']);
            $table->index(['platform_id', 'player_id']);
            $table->index(['platform_id', 'occurred_at']);
        });

        // 3. Webhook Ingestion Logs
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
            $table->string('external_event_id', 255)->nullable();
            $table->string('endpoint', 255);
            $table->boolean('signature_valid')->default(false);
            $table->string('processing_status', 50)->default('RECEIVED');
            $table->integer('http_status')->default(202);
            $table->json('payload');
            $table->json('headers');
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'signature_valid']);
            $table->index(['platform_id', 'processing_status']);
            $table->index(['platform_id', 'external_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_types');
    }
};
