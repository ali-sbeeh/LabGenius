<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order ;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
       $order = Order::inRandomOrder()->first() ?? Order::factory()->create();

    return [
        'order_id' => $order->id,
        'amount' => $order->total_price, // إرسال المبلغ المطلوب في الـ Migration
        'payment_method' => $this->faker->randomElement(['Cash on Delivery', 'Credit Card', 'Bank Transfer']),
        'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'refunded']),
        'transaction_id' => $this->faker->unique()->uuid(),
        'payment_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
    ];
    }
}
