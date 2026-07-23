<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order ;
use App\Models\OrderItem ;

class OrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::all();

        foreach ($orders as $order) {
            // إنشاء من 1 إلى 3 عناصر لكل طلب موجود
            OrderItem::factory(rand(1, 3))->create([
                'order_id' => $order->id
            ]);
        }
    }
}
