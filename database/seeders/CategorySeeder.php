<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Main Course', 'description' => 'Delicious main course dishes', 'shop_id' => '1', 'slug' => Str::slug('Main Course')],
            ['name' => 'Pasta', 'description' => 'Various types of pasta dishes', 'shop_id' => '1', 'slug' => Str::slug('Pasta')],
            ['name' => 'Sides', 'description' => 'Perfect sides to complement any dish', 'shop_id' => '1', 'slug' => Str::slug('Sides')],
            ['name' => 'Drinks', 'description' => 'Beverages to quench your thirst', 'shop_id' => '1', 'slug' => Str::slug('Drinks')],
            ['name' => 'Mocktail', 'description' => 'Non-alcoholic mocktails', 'shop_id' => '1', 'slug' => Str::slug('Mocktail')],
            ['name' => 'Cocktail', 'description' => 'Refreshing alcoholic cocktails', 'shop_id' => '1', 'slug' => Str::slug('Cocktail')],
            ['name' => 'Desserts', 'description' => 'Sweet treats to end your meal', 'shop_id' => '1', 'slug' => Str::slug('Desserts')],
            ['name' => 'Appetizers', 'description' => 'Start your meal with delicious appetizers', 'shop_id' => '1', 'slug' => Str::slug('Appetizers')],
            ['name' => 'Salads', 'description' => 'Fresh and healthy salads', 'shop_id' => '1', 'slug' => Str::slug('Salads')],
            ['name' => 'Soups', 'description' => 'Warm and comforting soups', 'shop_id' => '1', 'slug' => Str::slug('Soups')],
        ];

        foreach ($categories as &$category) {
            $category['created_at'] = Carbon::now();
            $category['updated_at'] = Carbon::now();
        }

        DB::table('categories')->insert($categories);
    }
}
