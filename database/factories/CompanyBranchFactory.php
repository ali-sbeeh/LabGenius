<?php

namespace Database\Factories;

use App\Models\CompanyBranch;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Province;
use App\Models\ShippingCompany;

/**
 * @extends Factory<CompanyBranch>
 */
class CompanyBranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار شركة شحن ومحافظة من الموجودين في قاعدة البيانات
            'shipping_company_id' => ShippingCompany::inRandomOrder()->first()->id ?? ShippingCompany::factory(),
            'province_id' => Province::inRandomOrder()->first()->id ?? Province::factory(),

            'branch_name' => 'فرع ' . $this->faker->streetName(),
            'address' => $this->faker->address(),
        ];
    }
}
