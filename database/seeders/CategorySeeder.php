<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;


class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'ألعاب (Gaming)'],
            ['name' => 'عمل مكتبي (Office)'],
            ['name' => 'تصميم جرافيك (Design)'],
            ['name' => 'برمجة (Programming)'],
            ['name' => 'دراسة (Study)'],
            ['name' => 'استخدام عام (General Use)'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
