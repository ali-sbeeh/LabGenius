<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إصلاح جداول الطلبات والدفع لدعم الطلبات بدون شركة شحن
     * وإضافة حقل إثبات الدفع
     */
    public function up(): void
    {
        // جعل shipping_company_id اختياري (nullable)
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_company_id')->nullable()->change();
            $table->text('note')->nullable()->after('shipping_address');
        });

        // إضافة حقل إثبات الدفع لجدول payments
        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_url')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_company_id')->nullable(false)->change();
            $table->dropColumn('note');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('proof_url');
        });
    }
};
