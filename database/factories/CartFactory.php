<?php

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار مستخدم عشوائي لم يمتلك سلة بعد، أو إنشاء مستخدم جديد
            'user_id' => User::where('role', 'customer')->inRandomOrder()->first()->id ?? User::factory(),
        ];
    }
}
