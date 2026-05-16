<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fields', 'maps_url')) {

            Schema::table('fields', function (Blueprint $table) {
                $table->text('maps_url')->nullable()->change();
            });

        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fields', 'maps_url')) {

            Schema::table('fields', function (Blueprint $table) {
                $table->string('maps_url', 1000)->nullable()->change();
            });

        }
    }
};