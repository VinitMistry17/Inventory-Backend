<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'icon' => 'electronics'],
            ['name' => 'Insurance', 'icon' => 'insurance'],
            ['name' => 'Documents', 'icon' => 'documents'],
            ['name' => 'Furniture', 'icon' => 'furniture'],
            ['name' => 'Books', 'icon' => 'books'],
            ['name' => 'Shoes', 'icon' => 'shoes'],
            ['name' => 'Bills', 'icon' => 'bills'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                [
                    'icon' => $category['icon'],
                    'is_custom' => false,
                    'user_id' => null,
                ]
            );
        }
    }
}
