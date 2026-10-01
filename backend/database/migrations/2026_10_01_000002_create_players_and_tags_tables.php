<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tags
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 30)->default('#10b981'); // Hex color code
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'name']);
            $table->index(['platform_id', 'created_at']);
        });

        // 2. Players
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->restrictOnDelete();
            $table->string('external_id', 100);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('cpf', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, INACTIVE, BLOCKED, PENDING, DELETED
            $table->string('source', 100)->nullable();
            $table->string('affiliate', 100)->nullable();
            $table->string('promo_code', 100)->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for high performance and deduplication
            $table->unique(['platform_id', 'external_id']);
            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'email']);
            $table->index(['platform_id', 'phone']);
            $table->index(['platform_id', 'state']);
            $table->index(['platform_id', 'city']);
            $table->index(['platform_id', 'affiliate']);
            $table->index(['platform_id', 'created_at']);
            $table->index(['platform_id', 'last_login_at']);
        });

        // PostgreSQL GIN Index for JSONB custom_fields
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX players_custom_fields_gin ON players USING gin(custom_fields);');
        }

        // 3. Player Tags (N:N)
        Schema::create('player_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['player_id', 'tag_id']);
            $table->index(['player_id']);
            $table->index(['tag_id']);
        });

        // 4. Consents (LGPD)
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('channel', 30); // EMAIL, SMS, WHATSAPP, PUSH
            $table->boolean('is_granted')->default(true);
            $table->timestamp('consent_date')->useCurrent();
            $table->string('consent_source', 100)->default('api');
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_version', 50)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'channel', 'is_granted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('player_tags');
        Schema::dropIfExists('players');
        Schema::dropIfExists('tags');
    }
};
