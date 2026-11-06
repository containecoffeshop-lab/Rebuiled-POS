<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Unit;
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
        foreach (['gram', 'ml', 'pcs', 'botol', 'bungkus'] as $unitName) {
            Unit::firstOrCreate(['name' => $unitName]);
        }

        $catalog = [
            'Kopi' => [
                ['name' => 'Kopi Susu Gula Aren', 'description' => 'Espresso, susu segar, dan manis gula aren.', 'selling_price' => 22000],
                ['name' => 'Americano', 'description' => 'Espresso dengan air panas, ringan dan seimbang.', 'selling_price' => 18000],
                ['name' => 'Cafe Latte', 'description' => 'Espresso dan susu steamed dengan rasa lembut.', 'selling_price' => 24000],
            ],
            'Non-kopi' => [
                ['name' => 'Matcha Latte', 'description' => 'Matcha pilihan dengan susu segar.', 'selling_price' => 26000],
                ['name' => 'Cokelat Hangat', 'description' => 'Cokelat pekat dengan susu.', 'selling_price' => 23000],
            ],
            'Makanan' => [
                ['name' => 'Roti Bakar Cokelat', 'description' => 'Roti panggang isi cokelat, disajikan hangat.', 'selling_price' => 20000],
                ['name' => 'Croissant Mentega', 'description' => 'Croissant renyah dengan mentega.', 'selling_price' => 19000],
            ],
        ];

        foreach ($catalog as $categoryName => $items) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($items as $item) {
                Menu::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $item['name']],
                    $item,
                );
            }
        }
    }
}
