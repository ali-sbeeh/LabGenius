<?php

namespace Database\Factories;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product ;
/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار منتج عشوائي من قاعدة البيانات
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),

            // توليد رابط صورة وهمي (يمكنك استبدالها بمسارات حقيقية لاحقاً)
            'image_path' => 'https://picsum.photos/seed/' . rand(1, 1000) . '/600/400',

            // تحديد ما إذا كانت هذه هي الصورة الأساسية للمنتج
            'is_primary' => false,
        ];
    }
}
