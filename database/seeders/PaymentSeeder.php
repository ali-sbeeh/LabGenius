<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order ;
use App\Models\Payment;
class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::all();

        foreach ($orders as $order) {
            // إنشاء سجل دفع لكل طلب
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => collect(['Cash on Delivery', 'Credit Card', 'Bank Transfer'])->random(),
                'status' => $order->status === 'delivered' ? 'completed' : 'pending',
                'transaction_id' => str()->uuid(),
                'payment_date' => $order->order_date,
            ]);
        }
    }
}
