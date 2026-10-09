<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('Europe/Zagreb');
        });

        Schema::create('training_types', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $table->string('name');
            $table->enum('mode', ['group', 'private']);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
        $this->check('training_types', 'positive_defaults', 'duration_minutes > 0 AND capacity > 0');

        Schema::create('instructor_profiles', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'user_id', 'users');
            $table->string('display_name');
            $table->text('biography')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $table->string('name');
            $table->unsignedSmallInteger('capacity');
            $table->timestamp('archived_at')->nullable();
        });
        $this->check('rooms', 'positive_capacity', 'capacity > 0');

        Schema::create('booking_rule_versions', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $table->unsignedInteger('version');
            $this->tenantForeign($table, 'created_by_user_id', 'users');
            $table->unsignedInteger('minimum_notice_minutes')->default(120);
            $table->unsignedSmallInteger('booking_horizon_days')->default(56);
            $table->unsignedInteger('group_cancellation_minutes')->default(720);
            $table->unsignedInteger('private_cancellation_minutes')->default(1440);
            $table->unsignedSmallInteger('studio_refund_validity_days')->default(7);
            $table->unique(['tenant_id', 'version']);
        });
        $this->check('booking_rule_versions', 'valid_version', 'version > 0 AND booking_horizon_days > 0 AND studio_refund_validity_days >= 7');

        Schema::create('schedule_series', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->resources($table);
            $table->string('timezone', 64);
            $table->json('weekdays');
            $table->time('local_start_time');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->timestamp('archived_at')->nullable();
        });
        $this->check('schedule_series', 'valid_period', '(ends_on IS NULL OR ends_on >= starts_on) AND duration_minutes > 0 AND capacity > 0');

        Schema::create('class_sessions', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->resources($table);
            $this->tenantForeign($table, 'schedule_series_id', 'schedule_series', true);
            $table->string('occurrence_key', 64)->nullable()->collation('utf8mb4_bin');
            $table->string('operation_key', 100)->nullable()->collation('utf8mb4_bin');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedSmallInteger('capacity');
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unique(['tenant_id', 'schedule_series_id', 'occurrence_key'], 'sessions_occurrence_unique');
            $table->unique(['tenant_id', 'operation_key']);
            $table->index(['tenant_id', 'status', 'starts_at']);
            $table->index(['tenant_id', 'instructor_profile_id', 'starts_at']);
            $table->index(['tenant_id', 'room_id', 'starts_at']);
        });
        $this->check('class_sessions', 'valid_interval', 'ends_at > starts_at AND capacity > 0 AND version > 0');
        $this->check('class_sessions', 'series_occurrence', '(schedule_series_id IS NULL) = (occurrence_key IS NULL)');

        Schema::create('availability_rules', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->resources($table);
            $table->string('timezone', 64);
            $table->unsignedTinyInteger('weekday');
            $table->time('local_start_time');
            $table->time('local_end_time');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('slot_step_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_before_minutes')->default(15);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(15);
            $table->timestamp('archived_at')->nullable();
        });
        $this->check('availability_rules', 'valid_window', 'weekday BETWEEN 1 AND 7 AND local_end_time > local_start_time AND (ends_on IS NULL OR ends_on >= starts_on) AND duration_minutes > 0 AND slot_step_minutes > 0');

        Schema::create('availability_exceptions', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'availability_rule_id', 'availability_rules');
            $table->date('local_date');
            $table->boolean('is_unavailable')->default(true);
            $table->time('local_start_time')->nullable();
            $table->time('local_end_time')->nullable();
            $table->text('reason')->nullable();
            $table->unique(['tenant_id', 'availability_rule_id', 'local_date'], 'availability_exception_date_unique');
        });
        $this->check('availability_exceptions', 'valid_override', '(is_unavailable = 1 AND local_start_time IS NULL AND local_end_time IS NULL) OR (is_unavailable = 0 AND local_start_time IS NOT NULL AND local_end_time IS NOT NULL AND local_end_time > local_start_time)');
    }

    public function down(): void
    {
        foreach (['availability_exceptions', 'availability_rules', 'class_sessions', 'schedule_series', 'booking_rule_versions', 'rooms', 'instructor_profiles', 'training_types'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }

    private function tenantColumns(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
        $table->unique(['tenant_id', 'id']);
        $table->timestamps();
    }

    private function tenantForeign(Blueprint $table, string $column, string $target, bool $nullable = false): void
    {
        $table->foreignId($column)->nullable($nullable);
        $table->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($target)->restrictOnDelete();
    }

    private function resources(Blueprint $table): void
    {
        $this->tenantForeign($table, 'training_type_id', 'training_types');
        $this->tenantForeign($table, 'instructor_profile_id', 'instructor_profiles');
        $this->tenantForeign($table, 'room_id', 'rooms');
        $this->tenantForeign($table, 'booking_rule_version_id', 'booking_rule_versions');
    }

    private function check(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$name} CHECK ({$expression})");
    }
};
