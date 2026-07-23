<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ✅ Admin
        User::firstOrCreate(
            ['email' => 'admin@lapgenius.com'],
            [
                'full_name' => 'Admin',
                'password' => Hash::make('demo123'),
                'role' => 'admin',
                'is_active' => true,
                'terms_accepted' => true,
            ]
        );

        // ✅ Seller
        User::firstOrCreate(
            ['email' => 'seller@lapgenius.com'],
            [
                'full_name' => 'Seller',
                'password' => Hash::make('demo123'),
                'role' => 'seller',
                'is_active' => true,
                'terms_accepted' => true,
            ]
        );

        // ✅ Customer
        User::firstOrCreate(
            ['email' => 'customer@lapgenius.com'],
            [
                'full_name' => 'Customer',
                'password' => Hash::make('demo123'),
                'role' => 'customer',
                'is_active' => true,
                'terms_accepted' => true,
            ]
        );

        // ✅ إنشاء 10 مستخدمين عشوائيين (بائعين وزبائن)
        // بس إذا ما بدك تكرار، استخدم firstOrCreate لكل واحد
        // User::factory(10)->create();
    }
}
