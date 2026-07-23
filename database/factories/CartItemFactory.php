<?php

namespace Database\Factories;

use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Cart;
use App\Models\Product ;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار سلة ومنتج بشكل عشوائي من الموجودين في قاعدة البيانات
            'cart_id' => Cart::inRandomOrder()->first()->id ?? Cart::factory(),
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 3), // كمية بين 1 و 3 قطع

        ];
    }
}
