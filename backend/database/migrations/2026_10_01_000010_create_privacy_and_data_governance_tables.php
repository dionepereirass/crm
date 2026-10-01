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
        // 1. Evolve 'consents' table
        Schema::table('consents', function (Blueprint $table) {
            $table->foreignId('platform_id')->nullable()->after('id')->constrained('platforms')->cascadeOnDelete();
            $table->string('type', 50)->nullable()->after('channel'); // MARKETING_EMAIL, MARKETING_SMS, etc.
            $table->string('status', 20)->default('GRANTED')->after('is_granted'); // GRANTED, REVOKED
            $table->string('user_agent', 500)->nullable()->after('consent_ip');
            $table->jsonb('evidence')->nullable()->after('consent_version');
            $table->string('evidence_hash', 64)->nullable()->after('evidence');
            $table->timestamp('granted_at')->nullable()->after('consent_date');

            $table->index(['platform_id', 'player_id', 'type', 'status']);
        });

        // 2. Create 'consent_history' (Append-Only)
        Schema::create('consent_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_id')->constrained('consents')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->string('action', 30); // GRANT, REVOKE, SYSTEM_UPDATE
            $table->string('source', 100)->nullable();
            $table->string('version', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('evidence')->nullable();
            $table->string('evidence_hash', 64)->nullable();
            $table->timestamp('performed_at')->useCurrent();

            $table->index(['platform_id', 'player_id']);
            $table->index(['consent_id']);
            $table->index(['performed_at']);
        });

        // 3. Create 'data_subject_requests' (LGPD Rights)
        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('type', 30); // ACCESS, CORRECTION, PORTABILITY, DELETION, REVOCATION, INFORMATION, ANONYMIZATION
            $table->string('status', 30)->default('OPEN'); // OPEN, IN_PROGRESS, COMPLETED, REJECTED, CANCELLED
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('due_at')->nullable(); // SLA deadline (e.g. 15 days)
            $table->timestamp('completed_at')->nullable();
            $table->string('requested_by', 100)->nullable(); // e.g. "player", "dpo@company.com"
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('resolution')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'type']);
            $table->index(['platform_id', 'player_id']);
            $table->index(['due_at']);
        });

        // 4. Create 'retention_policies'
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('data_category', 50); // LOGS, INACTIVE_PLAYERS, TRACKING_EVENTS, EXPORT_FILES
            $table->integer('retention_days')->default(365);
            $table->string('action', 20)->default('RETAIN'); // DELETE, ANONYMIZE, RETAIN
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['platform_id', 'data_category']);
            $table->index(['platform_id', 'active']);
        });

        // 5. Create 'audit_logs'
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->cascadeOnDelete();
            $table->string('actor_type', 30)->default('USER'); // USER, SYSTEM, API, PLAYER
            $table->string('actor_id', 100)->nullable();
            $table->string('action', 100); // LOGIN, LOGOUT, PLAYER_UPDATED, CONSENT_GRANTED, etc.
            $table->string('resource_type', 100)->nullable();
            $table->string('resource_id', 100)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['platform_id', 'action']);
            $table->index(['platform_id', 'resource_type', 'resource_id']);
            $table->index(['platform_id', 'created_at']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('consent_history');

        Schema::table('consents', function (Blueprint $table) {
            $table->dropIndex(['platform_id', 'player_id', 'type', 'status']);
            $table->dropForeign(['platform_id']);
            $table->dropColumn([
                'platform_id',
                'type',
                'status',
                'user_agent',
                'evidence',
                'evidence_hash',
                'granted_at',
            ]);
        });
    }
};
