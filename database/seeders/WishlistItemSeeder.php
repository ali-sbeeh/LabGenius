<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WishlistItem;

class WishlistItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // توليد 40 عنصراً وتوزيعهم عشوائياً على قوائم المفضلة
        WishlistItem::factory(40)->create();
    }
}
