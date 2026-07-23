<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CartItem;

class CartItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // توليد 30 عنصراً وتوزيعهم عشوائياً على السلال الموجودة
        CartItem::factory(30)->create();
    }
}
