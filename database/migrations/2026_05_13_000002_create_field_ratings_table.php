<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');   // 1–5
            $table->text('review')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            // Satu booking hanya boleh memberi satu rating
            $table->unique(['booking_id']);
            $table->index(['field_id', 'is_visible']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_ratings');
    }
};
