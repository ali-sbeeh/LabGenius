<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('igpu')->nullable()->after('gpu_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('image')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('igpu');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
