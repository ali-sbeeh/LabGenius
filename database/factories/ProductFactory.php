<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Category;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار بائع عشوائي من قاعدة البيانات
            'seller_id' => User::where('role', 'seller')->inRandomOrder()->first()->id ?? User::factory(),
            'category_id' => Category::inRandomOrder()->first()->id ?? Category::factory(),

            'name' => $this->faker->randomElement(['Dell XPS', 'HP Pavilion', 'MacBook Pro', 'Lenovo Legion', 'ASUS ROG', 'Acer Swift']),
            'price' => $this->faker->randomFloat(2, 400, 5000), // أسعار تتراوح بين 400 و 5000 دولار
            'brand' => $this->faker->randomElement(['Dell', 'HP', 'Apple', 'Lenovo', 'ASUS', 'Acer']),
            'stock_quantity' => $this->faker->numberBetween(1, 20),
            'condition' => $this->faker->randomElement(['new', 'used', 'refurbished']),
            'description' => $this->faker->paragraph(),

            // المواصفات التقنية (مهمة للمساعد الذكي وتقدير السعر)
            'cpu_type' => $this->faker->randomElement(['Intel Core i5', 'Intel Core i7', 'Intel Core i9', 'AMD Ryzen 5', 'AMD Ryzen 7', 'Apple M2']),
            'ram_size' => $this->faker->randomElement(['8GB', '16GB', '32GB', '64GB']),
            'gpu_type' => $this->faker->randomElement(['NVIDIA RTX 3060', 'NVIDIA RTX 4070', 'AMD Radeon RX 6000', 'Integrated Graphics']),
            'storage_size' => $this->faker->randomElement(['256GB SSD', '512GB SSD', '1TB SSD', '2TB SSD']),
            'screen_size' => $this->faker->randomElement(['13.3"', '14"', '15.6"', '16"', '17.3"']),
            'battery_capacity' => $this->faker->numberBetween(40, 99) . 'Wh',
            'os' => $this->faker->randomElement(['Windows 11', 'macOS', 'Linux', 'FreeDOS']),
            'weight' => $this->faker->randomFloat(2, 1.1, 3.5),
        ];
    }
}
