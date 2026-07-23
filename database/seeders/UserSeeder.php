<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User ;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'full_name' => 'Admin',
            'email' => 'admin@lapgenius.com',
            'password' => \Illuminate\Support\Facades\Hash::make('demo123'),
            'role' => 'admin'
        ]);

        User::factory()->create([
            'full_name' => 'Seller',
            'email' => 'seller@lapgenius.com',
            'password' => \Illuminate\Support\Facades\Hash::make('demo123'),
            'role' => 'seller',
        ]);

        User::factory()->create([
            'full_name' => 'Customer',
            'email' => 'customer@lapgenius.com',
            'password' => \Illuminate\Support\Facades\Hash::make('demo123'),
            'role' => 'customer',
        ]);

        // إنشاء 10 مستخدمين عشوائيين (بائعين وزبائن)
        User::factory(10)->create();
    }
}
