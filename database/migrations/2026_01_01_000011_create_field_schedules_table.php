<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->nullable();   // 0=Sun…6=Sat
            $table->date('specific_date')->nullable();
            $table->time('open_time')->default('08:00:00');
            $table->time('close_time')->default('22:00:00');
            $table->unsignedInteger('price_per_hour')->nullable(); // override price
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_schedules');
    }
};
