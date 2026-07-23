<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingCompany;
class ShippingCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // شركات شحن مقترحة للتطبيق
        $companies = [
            ['name' => 'شركة القدموس'],
            ['name' => 'شركة الهرم'],
            ['name' => 'شركة المشتري'],
            ['name' => 'دي إتش إل (DHL)'],
            ['name' => 'أرامكس (Aramex)'],
        ];

        foreach ($companies as $company) {
            ShippingCompany::create($company);
        }

        //  إضافة شركات عشوائية إافية
        // ShippingCompany::factory(3)->create();
    }

}
