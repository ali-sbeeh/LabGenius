<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductImage;
use App\Models\Product;
class ProductImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            // إنشاء صورة أساسية لكل منتج
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => 'https://picsum.photos/seed/main_' . $product->id . '/600/400',
                'is_primary' => true,
            ]);

            // إضافة صورتين إضافيتين (معرض الصور) لكل منتج
            ProductImage::factory(2)->create([
                'product_id' => $product->id,
                'is_primary' => false,
            ]);
         }
    }
}
