<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Province;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // قائمة بالمحافظات السورية الأساسية لضمان واقعية النظام
        $provinces = [
            ['name' => 'اللاذقية'],
            ['name' => 'دمشق'],
            ['name' => 'حلب'],
            ['name' => 'حمص'],
            ['name' => 'طرطوس'],
            ['name' => 'حماة'],
            ['name' => 'ريف دمشق'],
            ['name' => 'السويداء'],
            ['name' => 'درعا'],
            ['name' => 'القنيطرة'],
            ['name' => 'دير الزور'],
            ['name' => 'الرقة'],
            ['name' => 'الحسكة'],
            ['name' => 'إدلب'],
        ];

        foreach ($provinces as $province) {
            Province::create($province);
        }

        // إذا أردت إضافة محافظات عشوائية إضافية للاختبار:
        // Province::factory(5)->create();
    }

}
