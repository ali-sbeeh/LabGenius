<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Discount ;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // تطبيق خصومات على 15 منتجاً عشوائياً فقط لجعل العروض مميزة
        Discount::factory(15)->create();
    }
}
