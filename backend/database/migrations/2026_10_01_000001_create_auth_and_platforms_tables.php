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
        // 1. Platforms (Multi-Tenancy)
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, INACTIVE, SUSPENDED
            $table->string('api_key', 255)->unique();
            $table->string('webhook_secret', 255);
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        // 2. Users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, INACTIVE, BLOCKED
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Platform Users (N:N relationship between users and platforms)
        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['platform_id', 'user_id']);
        });

        // 4. Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // e.g. 'Super Admin'
            $table->string('slug', 50)->unique(); // SUPER_ADMIN, ADMIN, MARKETING, SUPPORT, ANALYST
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // 5. Permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique(); // e.g. 'users.view', 'campaigns.send'
            $table->string('group', 50); // 'users', 'platforms', 'players', 'campaigns', 'reports', 'settings'
            $table->timestamps();
        });

        // 6. Role User (N:N)
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['role_id', 'user_id']);
        });

        // 7. Permission Role (N:N)
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['permission_id', 'role_id']);
        });

        // 8. Personal Access Tokens (Sanctum)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('platform_users');
        Schema::dropIfExists('users');
        Schema::dropIfExists('platforms');
    }
};
