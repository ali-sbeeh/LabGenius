<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Product ;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار زبون ومنتج بشكل عشوائي من قاعدة البيانات
            'user_id' => User::where('role', 'customer')->inRandomOrder()->first()->id ?? User::factory(),
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),

            'rating' => $this->faker->numberBetween(1, 5), // التقييم بالنجوم
            'comment' => $this->faker->sentence(10), // تعليق نصي عشوائي
        ];
    }
}
