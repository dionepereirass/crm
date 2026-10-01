<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Phase 12: Scheduled Reports, Alert Rules and Operational Alerts.
     */
    public function up(): void
    {
        // 1. Scheduled Reports Table
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('report_type', 50); // PLAYERS, FINANCIAL, MARKETING, BETTING, AUTOMATIONS, PRIVACY
            $table->string('frequency', 30); // DAILY, WEEKLY, MONTHLY
            $table->json('recipients'); // Array of email addresses
            $table->json('filters')->nullable(); // Configured date and segment filters
            $table->string('format', 20)->default('CSV'); // CSV, JSON
            $table->boolean('active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['platform_id', 'report_type']);
            $table->index(['platform_id', 'active']);
            $table->index(['next_run_at']);
        });

        // 2. Alert Rules Table
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('metric', 80); // e.g. PROVIDER_FAILURES, DELIVERY_DROP, CHURN_INCREASE, INACTIVE_PLAYERS, etc.
            $table->string('operator', 10); // GT, GTE, LT, LTE, EQ
            $table->decimal('threshold', 14, 2);
            $table->string('severity', 20)->default('WARNING'); // INFO, WARNING, CRITICAL
            $table->integer('cooldown_minutes')->default(60); // Anti-spam delay
            $table->boolean('active')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'metric']);
            $table->index(['platform_id', 'active']);
        });

        // 3. Operational Alerts Table
        Schema::create('operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->nullable()->constrained('alert_rules')->nullOnDelete();
            $table->string('metric', 80);
            $table->string('severity', 20); // INFO, WARNING, CRITICAL
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, ACKNOWLEDGED, RESOLVED
            $table->string('title', 200);
            $table->text('message');
            $table->decimal('current_value', 14, 2)->nullable();
            $table->decimal('threshold_value', 14, 2)->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'severity']);
            $table->index(['platform_id', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('scheduled_reports');
    }
};
