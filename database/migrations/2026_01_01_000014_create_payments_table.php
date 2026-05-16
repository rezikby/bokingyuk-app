<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method')->nullable();          // qris, bca_va, gopay, etc.
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->json('gateway_response')->nullable();
            $table->string('status')->default('unpaid');
            $table->string('snap_token')->nullable();
            $table->string('payment_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
