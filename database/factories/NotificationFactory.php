<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User ;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // اختيار مستخدم عشوائي (زبون، بائع، أو أدمن) لاستقبال الإشعار
            'user_id' => User::inRandomOrder()->first()->id ?? User::factory(),

            'title' => $this->faker->randomElement([
                'طلب جديد',
                'تم تحديث حالة الطلب',
                'عرض جديد متاح',
                'تمت إضافة مراجعة لمنتجك'
            ]),
            'message' => $this->faker->sentence(12),
            'is_read' => $this->faker->boolean(20), // نسبة 20% أن الإشعار مقروء
            'type' => $this->faker->randomElement(['order', 'offer', 'system', 'review']),
        ];
    }
}
