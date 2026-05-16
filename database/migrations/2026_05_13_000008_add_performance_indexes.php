<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fitur 8: Optimasi Query - tambah indexes yang sering di-query
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'bookings_user_status_idx');
            $table->index(['field_id', 'booking_date', 'status'], 'bookings_field_date_status_idx');
            $table->index(['payment_status'], 'bookings_payment_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'expired_at'], 'payments_status_expired_idx');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'created_at'], 'activity_logs_user_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_user_status_idx');
            $table->dropIndex('bookings_field_date_status_idx');
            $table->dropIndex('bookings_payment_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_expired_idx');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_user_type_idx');
        });
    }
};
