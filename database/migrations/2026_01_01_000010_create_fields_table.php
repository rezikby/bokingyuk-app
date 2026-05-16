<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');          // futsal, badminton, etc.
            $table->text('description')->nullable();
            $table->unsignedInteger('price_per_hour');
            $table->boolean('is_active')->default(true);
            $table->string('image')->nullable();
            $table->json('facilities')->nullable();
            // Location fields
            $table->string('address')->nullable();
            $table->string('maps_url')->nullable();       // Google Maps embed URL or link
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
