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
        // 1. Segments Table
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade');
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('DRAFT'); // DRAFT, ACTIVE, INACTIVE
            $table->json('rules_tree');
            $table->integer('cached_count')->default(0);
            $table->timestamp('cached_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'slug']);
        });

        // 2. Segment Groups (Aninhamento de regras lógicas AND / OR)
        Schema::create('segment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('segment_groups')->onDelete('cascade');
            $table->string('logical_operator', 10)->default('AND'); // AND, OR
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('segment_id');
            $table->index('parent_id');
        });

        // 3. Segment Conditions (Critérios atômicos de filtragem)
        Schema::create('segment_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('segment_groups')->onDelete('cascade');
            $table->string('field', 100);
            $table->string('operator', 50);
            $table->text('value')->nullable();
            $table->string('value_type', 30)->default('STRING'); // STRING, INTEGER, DECIMAL, BOOLEAN, DATE, DATETIME, ENUM, JSON, EVENT
            $table->string('event_type', 100)->nullable();
            $table->integer('period_value')->nullable();
            $table->string('period_unit', 20)->nullable(); // hours, days, weeks, months
            $table->integer('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('segment_id');
            $table->index('group_id');
            $table->index('field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segment_conditions');
        Schema::dropIfExists('segment_groups');
        Schema::dropIfExists('segments');
    }
};
