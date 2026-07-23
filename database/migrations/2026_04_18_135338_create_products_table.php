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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('brand');
            $table->integer('stock_quantity');
            $table->enum('condition', ['new', 'used', 'refurbished']);
            $table->text('description')->nullable();

            $table->string('cpu_type');
            $table->string('ram_size');
            $table->string('gpu_type');
            $table->string('storage_size');
            $table->string('screen_size');
            $table->string('battery_capacity');
            $table->string('os');
            $table->decimal('weight', 5, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
