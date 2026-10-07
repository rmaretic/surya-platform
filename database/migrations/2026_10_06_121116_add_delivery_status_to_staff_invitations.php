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
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'delivery_failed_at']);
        });
    }
};
