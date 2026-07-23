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
       Schema::create('company_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_company_id')->constrained('shipping_companies')->onDelete('cascade');
            $table->foreignId('province_id')->constrained('provinces'); // يتبع لمحافظة معينة
            $table->string('branch_name');
            $table->string('address');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
