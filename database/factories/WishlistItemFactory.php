<?php

namespace Database\Factories;

use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\Wishlist;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار قائمة مفضلة ومنتج بشكل عشوائي من قاعدة البيانات
            'wishlist_id' => Wishlist::inRandomOrder()->first()->id ?? Wishlist::factory(),
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),
        ];
    }
}
