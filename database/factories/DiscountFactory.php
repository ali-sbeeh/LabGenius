<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
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

            // قيمة الخصم (نسبة مئوية بين 5% و 40%)
            'discount_percent' => $this->faker->numberBetween(5, 40),

            // تاريخ البدء والانتهاء
            'start_date' => now(),
            'end_date' => $this->faker->dateTimeBetween('now', '+1 month'),

            'description' => $this->faker->randomElement(['عرض الموسم', 'تصفية', 'خصم خاص للطلاب', 'عرض العيد']),

        ];
    }
}
