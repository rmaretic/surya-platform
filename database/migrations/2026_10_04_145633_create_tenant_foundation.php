<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Assign existing users to verified tenants through an explicit data migration before installing the tenant foundation.');
        }

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->string('design_key');
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('hostname', 253);
            $table->string('normalized_hostname', 253)->collation('utf8mb4_bin')
                ->storedAs("LOWER(TRIM(TRAILING '.' FROM TRIM(hostname)))")->unique();
            $table->enum('verification_status', ['pending', 'verified'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_tenant_id')
                ->nullable()->storedAs('CASE WHEN is_primary = 1 THEN tenant_id ELSE NULL END')->unique();
            $table->timestamps();
        });

        Schema::create('tenant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->dropUnique(['email']);
            $table->string('normalized_email')->collation('utf8mb4_bin')->storedAs('LOWER(TRIM(email))');
            $table->enum('role', ['owner', 'manager', 'instructor', 'customer'])->default('customer');
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'normalized_email']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('platform_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('normalized_email')->collation('utf8mb4_bin')->storedAs('LOWER(TRIM(email))')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('staff_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->string('normalized_email')->collation('utf8mb4_bin')->storedAs('LOWER(TRIM(email))');
            $table->enum('role', ['owner', 'manager', 'instructor']);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_user_id')->nullable();
            $table->foreignId('invited_by_platform_admin_id')->nullable()->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'normalized_email']);
            $table->foreign(['tenant_id', 'invited_by_user_id'], 'invitations_inviter_tenant_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'accepted_by_user_id'], 'invitations_recipient_tenant_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE staff_invitations ADD CONSTRAINT invitations_one_author CHECK ((invited_by_user_id IS NULL) <> (invited_by_platform_admin_id IS NULL))');

        Schema::create('tenant_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->foreign(['tenant_id', 'actor_user_id'], 'audit_actor_tenant_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_platform_admin_id')->nullable()->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('target_tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        Schema::create('tenant_password_reset_tokens', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('email')->collation('utf8mb4_bin');
            $table->string('normalized_email')->collation('utf8mb4_bin')->storedAs('LOWER(TRIM(email))');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->primary(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'normalized_email'], 'tenant_reset_email_unique');
        });

        Schema::create('platform_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('normalized_email')->collation('utf8mb4_bin')->storedAs('LOWER(TRIM(email))')->unique();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('tenant_sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            $table->foreign(['tenant_id', 'user_id'], 'session_user_tenant_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE tenant_sessions ADD CONSTRAINT sessions_authenticated_tenant CHECK (user_id IS NULL OR tenant_id IS NOT NULL)');

        Schema::create('platform_sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('platform_admins')->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        if (DB::table('tenants')->exists() || DB::table('platform_admins')->exists()) {
            throw new RuntimeException('The tenant foundation contains data. Use a reviewed forward migration instead of rolling it back.');
        }

        foreach (['platform_sessions', 'tenant_sessions', 'platform_password_reset_tokens', 'tenant_password_reset_tokens', 'platform_audit_logs', 'tenant_audit_logs', 'staff_invitations', 'platform_admins'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id', 'normalized_email']);
            $table->dropUnique(['tenant_id', 'id']);
            $table->dropColumn(['tenant_id', 'normalized_email', 'role', 'is_active']);
            $table->unique('email');
        });

        Schema::dropIfExists('tenant_profiles');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
