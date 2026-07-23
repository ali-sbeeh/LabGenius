<?php

namespace Database\Factories;

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User  ;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار زبون عشوائي لم يمتلك قائمة مفضلة بعد
            'user_id' => User::where('role', 'customer')->inRandomOrder()->first()->id ?? User::factory(),
        ];
    }
}
