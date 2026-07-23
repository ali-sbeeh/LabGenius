<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_admin_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // create_customer, delete_product, etc.
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_type')->nullable(); // user, product, category, order
            $table->text('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['action', 'created_at']);
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};
