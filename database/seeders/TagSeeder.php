<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /** @var list<string> */
    private const TAGS = [
        'Mediterranean', 'High Protein', 'Healthy', 'Quick', 'Meal Prep',
        'Vegetarian', 'Croatian', 'Comfort Food', 'Summer', 'Holiday',
        'One Pan', 'Make Ahead', 'Seafood', 'Grill',
    ];

    public function run(): void
    {
        foreach (self::TAGS as $name) {
            Tag::query()->updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
