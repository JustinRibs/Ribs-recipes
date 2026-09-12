<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe in every environment: the owner account plus the starting
        // taxonomy, all idempotent.
        $this->call([
            OwnerSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
        ]);

        // Demo content is development-only; it would be noise in production.
        if (! app()->environment('production')) {
            $this->call(DemoRecipeSeeder::class);
        }
    }
}
