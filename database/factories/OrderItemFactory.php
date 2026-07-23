<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use App\Models\Product ;
/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // اختيار منتج عشوائي لجلب سعره
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        return [
            // اختيار طلب عشوائي
            'order_id' => Order::inRandomOrder()->first()->id ?? Order::factory(),
            'product_id' => $product->id,

            'quantity' => $this->faker->numberBetween(1, 2),
            // تسجيل سعر المنتج في لحظة البيع
            'price_at_purchase' => $product->price,
        ];
    }
}
