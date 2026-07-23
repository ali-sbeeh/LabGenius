<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User ;
use App\Models\Cart ;

class CartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // الحصول على جميع المستخدمين الذين دورهم "زبون"
        $customers = User::where('role', 'customer')->get();

        foreach ($customers as $customer) {
            // إنشاء سلة لكل زبون إذا لم تكن موجودة
            Cart::firstOrCreate(['user_id' => $customer->id]);
        }
    }
}
