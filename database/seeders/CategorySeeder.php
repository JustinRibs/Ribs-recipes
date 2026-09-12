<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The starting category set. Fully editable in the admin afterwards — this is
 * a sensible default, not a fixed taxonomy.
 *
 * `icon` is a key into the front-end's hand-picked Lucide set, and `color` is
 * the accent used for the category chip.
 */
class CategorySeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private const CATEGORIES = [
        ['name' => 'Breakfast', 'slug' => 'breakfast', 'icon' => 'sunrise', 'color' => '#C98A2E',
            'description' => 'Mornings worth getting up for.'],
        ['name' => 'Lunch', 'slug' => 'lunch', 'icon' => 'sandwich', 'color' => '#5B8C5A',
            'description' => 'Midday plates, desk-friendly and otherwise.'],
        ['name' => 'Dinner', 'slug' => 'dinner', 'icon' => 'utensils', 'color' => '#8C2F39',
            'description' => 'The main event, from weeknight to Sunday.'],
        ['name' => 'Dessert', 'slug' => 'dessert', 'icon' => 'cake', 'color' => '#B4607A',
            'description' => 'Sweet endings, most of them simple.'],
        ['name' => 'Drinks', 'slug' => 'drinks', 'icon' => 'cup-soda', 'color' => '#2E6F95',
            'description' => 'Cold, warm and everything to pour.'],
        ['name' => 'Snacks', 'slug' => 'snacks', 'icon' => 'cookie', 'color' => '#A0703A',
            'description' => 'Small things to keep around.'],
        ['name' => 'Sauces & Dressings', 'slug' => 'sauces-dressings', 'icon' => 'droplets', 'color' => '#4C7A4C',
            'description' => 'The jars that make everything else better.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => ($index + 1) * 10],
            );
        }
    }
}
