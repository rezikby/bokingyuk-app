<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');          // initiated | paid | expired | refunded | failed
            $table->string('gateway_status')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('payment_method')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_histories');
    }
};
