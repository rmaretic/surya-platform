<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_products', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->enum('currency', ['EUR'])->default('EUR');
            $table->unsignedInteger('credits');
            $table->unsignedSmallInteger('validity_days');
            $table->timestamp('archived_at')->nullable();
        });
        $this->check('package_products', 'positive_entitlement', 'credits > 0 AND validity_days > 0');

        Schema::create('package_product_training_type', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $this->tenantForeign($table, 'package_product_id', 'package_products', false, 'product_types_product_fk');
            $this->tenantForeign($table, 'training_type_id', 'training_types', false, 'product_types_type_fk');
            $table->primary(['tenant_id', 'package_product_id', 'training_type_id'], 'product_types_primary');
        });

        Schema::create('credit_grants', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'user_id', 'users');
            $this->tenantForeign($table, 'package_product_id', 'package_products');
            $this->tenantForeign($table, 'created_by_user_id', 'users');
            $table->enum('source', ['demo', 'external_purchase', 'complimentary']);
            $table->string('source_reference')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('granted_at');
            $table->dateTime('valid_from');
            $table->dateTime('expires_at');
            $table->unsignedInteger('credits');
            $table->json('product_snapshot');
            $table->string('operation_key', 100)->collation('utf8mb4_bin');
            $table->foreignId('compensates_credit_entry_id')->nullable();
            $table->unique(['tenant_id', 'operation_key']);
            $table->unique(['tenant_id', 'user_id', 'id'], 'grants_owner_unique');
            $table->index(['tenant_id', 'user_id', 'expires_at']);
        });
        $this->check('credit_grants', 'valid_entitlement', 'credits > 0 AND expires_at > valid_from');
        $this->check('credit_grants', 'documented_source', "(source <> 'external_purchase' OR (source_reference IS NOT NULL AND CHAR_LENGTH(TRIM(source_reference)) > 0)) AND (source <> 'complimentary' OR (reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0))");

        Schema::create('credit_grant_training_type', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $this->tenantForeign($table, 'credit_grant_id', 'credit_grants', false, 'grant_types_grant_fk');
            $this->tenantForeign($table, 'training_type_id', 'training_types', false, 'grant_types_type_fk');
            $table->primary(['tenant_id', 'credit_grant_id', 'training_type_id'], 'grant_types_primary');
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'user_id', 'users');
            $this->tenantForeign($table, 'class_session_id', 'class_sessions');
            $this->tenantForeign($table, 'booking_rule_version_id', 'booking_rule_versions');
            $this->tenantForeign($table, 'created_by_user_id', 'users');
            $this->tenantForeign($table, 'cancelled_by_user_id', 'users', true);
            $table->foreignId('credit_grant_id');
            $table->foreign(['tenant_id', 'user_id', 'credit_grant_id'], 'bookings_grant_owner_fk')
                ->references(['tenant_id', 'user_id', 'id'])->on('credit_grants')->restrictOnDelete();
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->string('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('confirmed_at');
            $table->json('rules_snapshot');
            $table->unsignedSmallInteger('credits_used')->default(1);
            $table->string('operation_key', 100)->collation('utf8mb4_bin');
            $table->unsignedTinyInteger('active_slot')->nullable()
                ->storedAs("CASE WHEN status = 'confirmed' THEN 1 ELSE NULL END");
            $table->unique(['tenant_id', 'class_session_id', 'user_id', 'active_slot'], 'bookings_active_unique');
            $table->unique(['tenant_id', 'operation_key']);
            $table->unique(['tenant_id', 'credit_grant_id', 'id'], 'bookings_grant_unique');
            $table->index(['tenant_id', 'user_id', 'status']);
        });
        $this->check('bookings', 'one_credit', 'credits_used = 1');
        $this->check('bookings', 'cancellation_details', "(status = 'confirmed' AND cancelled_at IS NULL AND cancellation_reason IS NULL AND cancelled_by_user_id IS NULL) OR (status = 'cancelled' AND cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL AND CHAR_LENGTH(TRIM(cancellation_reason)) > 0)");

        Schema::create('credit_entries', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'credit_grant_id', 'credit_grants');
            $this->tenantForeign($table, 'created_by_user_id', 'users');
            $table->foreignId('booking_id')->nullable();
            $table->foreign(['tenant_id', 'credit_grant_id', 'booking_id'], 'entries_booking_grant_fk')
                ->references(['tenant_id', 'credit_grant_id', 'id'])->on('bookings')->restrictOnDelete();
            $table->enum('kind', ['grant', 'debit', 'reversal', 'adjustment']);
            $table->integer('amount');
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at');
            $table->string('operation_key', 100)->collation('utf8mb4_bin');
            $table->foreignId('reverses_entry_id')->nullable();
            $table->unique(['tenant_id', 'operation_key']);
            $table->unique(['tenant_id', 'credit_grant_id', 'id'], 'entries_grant_unique');
            $table->unique(['tenant_id', 'reverses_entry_id']);
            $table->foreign(['tenant_id', 'credit_grant_id', 'reverses_entry_id'], 'entries_reversal_grant_fk')
                ->references(['tenant_id', 'credit_grant_id', 'id'])->on('credit_entries')->restrictOnDelete();
            $table->index(['tenant_id', 'credit_grant_id', 'occurred_at']);
        });
        $this->check('credit_entries', 'valid_movement', "(kind = 'grant' AND amount > 0 AND booking_id IS NULL AND reverses_entry_id IS NULL) OR (kind = 'debit' AND amount = -1 AND booking_id IS NOT NULL AND reverses_entry_id IS NULL) OR (kind = 'reversal' AND amount = 1 AND booking_id IS NOT NULL AND reverses_entry_id IS NOT NULL) OR (kind = 'adjustment' AND amount <> 0 AND booking_id IS NULL AND reverses_entry_id IS NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0)");

        Schema::table('credit_grants', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'compensates_credit_entry_id'], 'grants_compensation_entry_fk')
                ->references(['tenant_id', 'id'])->on('credit_entries')->restrictOnDelete();
            $table->unique(['tenant_id', 'compensates_credit_entry_id'], 'grants_compensation_unique');
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'booking_id', 'bookings');
            $this->tenantForeign($table, 'recorded_by_user_id', 'users', true);
            $table->enum('status', ['pending', 'present', 'no_show'])->default('pending');
            $table->dateTime('recorded_at')->nullable();
            $table->text('correction_reason')->nullable();
            $table->unique(['tenant_id', 'booking_id']);
        });
        $this->check('attendance_records', 'recorded_actor', "(status = 'pending' AND recorded_at IS NULL AND recorded_by_user_id IS NULL) OR (status <> 'pending' AND recorded_at IS NOT NULL AND recorded_by_user_id IS NOT NULL)");

        Schema::create('outbox_events', function (Blueprint $table): void {
            $this->tenantColumns($table);
            $this->tenantForeign($table, 'booking_id', 'bookings', true);
            $this->tenantForeign($table, 'class_session_id', 'class_sessions', true);
            $this->tenantForeign($table, 'recipient_user_id', 'users');
            $table->string('event_type', 100);
            $table->unsignedInteger('aggregate_version');
            $table->string('deduplication_key', 191)->collation('utf8mb4_bin');
            $table->json('payload');
            $table->dateTime('available_at');
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->enum('status', ['pending', 'processing', 'delivered', 'failed', 'uncertain'])->default('pending');
            $table->string('last_error_code', 100)->nullable();
            $table->unique(['tenant_id', 'deduplication_key']);
            $table->index(['tenant_id', 'status', 'available_at']);
        });
        $this->check('outbox_events', 'valid_subject', '(booking_id IS NOT NULL OR class_session_id IS NOT NULL) AND aggregate_version > 0');

    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('attendance_records');
        Schema::table('credit_grants', function (Blueprint $table): void {
            $table->dropForeign('grants_compensation_entry_fk');
        });
        foreach (['credit_entries', 'bookings', 'credit_grant_training_type', 'credit_grants', 'package_product_training_type', 'package_products'] as $name) {
            Schema::dropIfExists($name);
        }
    }

    private function tenantColumns(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
        $table->unique(['tenant_id', 'id']);
        $table->timestamps();
    }

    private function tenantForeign(Blueprint $table, string $column, string $target, bool $nullable = false, ?string $name = null): void
    {
        $table->foreignId($column)->nullable($nullable);
        $table->foreign(['tenant_id', $column], $name)->references(['tenant_id', 'id'])->on($target)->restrictOnDelete();
    }

    private function check(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$name} CHECK ({$expression})");
    }
};
