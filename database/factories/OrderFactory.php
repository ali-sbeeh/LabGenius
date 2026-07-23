<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User ;
use App\Models\ShippingCompany;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار زبون عشوائي وشركة شحن
            'user_id' => User::where('role', 'customer')->inRandomOrder()->first()->id ?? User::factory(),
            'shipping_company_id' => ShippingCompany::inRandomOrder()->first()->id ?? ShippingCompany::factory(),

            'total_price' => $this->faker->randomFloat(2, 500, 10000),
            'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
            'shipping_address' => $this->faker->address(),
            'order_date' => $this->faker->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
