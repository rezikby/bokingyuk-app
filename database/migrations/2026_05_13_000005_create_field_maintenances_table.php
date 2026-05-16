<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('scheduled'); // scheduled | in_progress | completed | cancelled
            $table->timestamps();

            $table->index(['field_id', 'maintenance_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_maintenances');
    }
};
