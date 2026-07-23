<?php

namespace Database\Seeders;

use App\Models\CompanyBranch;
use App\Models\User;
use Faker\Provider\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       /* User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
      */
        $this->call([
            ProvinceSeeder::class ,
            CategorySeeder::class ,
            ShippingCompanySeeder::class,
            CompanyBranchSeeder::class ,
            UserSeeder::class,
            ProductSeeder::class ,
            CartSeeder::class ,
            WishlistSeeder::class ,
            CartItemSeeder::class ,
            WishlistItemSeeder::class,
            ReviewSeeder::class ,
            ProductImageSeeder::class,
            DiscountSeeder::class,
            NotificationSeeder::class ,
            OrderSeeder::class ,
            OrderItemSeeder::class ,
            PaymentSeeder::class
        ]);
    }
}
