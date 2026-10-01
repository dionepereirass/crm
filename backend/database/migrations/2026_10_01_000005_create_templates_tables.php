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
        // 1. Templates Table
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->text('description')->nullable();
            $table->string('channel', 30); // EMAIL, SMS
            $table->string('status', 30)->default('DRAFT'); // DRAFT, ACTIVE, ARCHIVED
            $table->string('category', 50)->default('GENERAL'); // MARKETING, TRANSACTIONAL, RETENTION, PROMOTION, WELCOME, DEPOSIT, BETTING, WITHDRAWAL, ACCOUNT, GENERAL
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform_id', 'status']);
            $table->index(['platform_id', 'channel']);
            $table->index(['platform_id', 'category']);
            $table->index(['platform_id', 'slug']);
        });

        // 2. Template Versions Table (Histórico e Imutabilidade)
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->string('status', 30)->default('DRAFT'); // DRAFT, PUBLISHED, ARCHIVED
            $table->string('subject', 255)->nullable();
            $table->string('preheader', 255)->nullable();
            $table->longText('html_content')->nullable();
            $table->longText('text_content')->nullable();
            $table->text('sms_content')->nullable();
            $table->json('variables_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'version']);
            $table->index(['template_id', 'status']);
        });

        // 3. Vincular Foreign Key de current_version_id evitando dependência circular
        Schema::table('templates', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('template_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('templates');
    }
};
