<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            // admin_id: siapa admin pemilik lapangan ini
            // NULL berarti belum diassign (data lama sebelum migrasi)
            $table->unsignedBigInteger('admin_id')->nullable()->after('id');
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });
    }
};
