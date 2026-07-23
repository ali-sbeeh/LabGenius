<?php

namespace Database\Factories;

use App\Models\ShippingCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingCompany>
 */
class ShippingCompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // توليد اسم شركة شحن عشوائي مثل "Fast Express" أو "Syria Post"
            'name' => fake()->company() . ' Shipping',
        ];
    }
}
