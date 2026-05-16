<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');              // login, logout, booking_create, dll
            $table->string('description');       // teks deskripsi yang ditampilkan
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->json('metadata')->nullable(); // data tambahan (opsional)
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};