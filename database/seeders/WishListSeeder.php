<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wishlist;

class WishListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // جلب كافة الزبائن
        $customers = User::where('role', 'customer')->get();

        foreach ($customers as $customer) {
            // إنشاء قائمة مفضلة لكل زبون إذا لم تكن موجودة مسبقاً
            Wishlist::firstOrCreate(['user_id' => $customer->id]);
        }
    }
}
