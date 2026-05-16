<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('duration_hours');
            $table->unsignedInteger('total_price');
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->uuid('qr_token')->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_date', 'field_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
