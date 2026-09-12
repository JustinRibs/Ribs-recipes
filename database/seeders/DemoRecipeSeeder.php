<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecipeStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Images\ImageStore;
use App\Services\Recipes\RecipeWriter;
use App\Support\Quantity;
use Illuminate\Database\Seeder;

/**
 * Fills a development database with a realistic collection.
 *
 * Photography is generated locally by DemoImageFactory, so nothing here
 * depends on a remote image host, and the real upload pipeline runs on every
 * seeded photo.
 */
class DemoRecipeSeeder extends Seeder
{
    public function run(): void
    {
        /** @var RecipeWriter $writer */
        $writer = app(RecipeWriter::class);
        /** @var ImageStore $images */
        $images = app(ImageStore::class);

        $author = User::query()->orderBy('id')->first()
            ?? User::factory()->owner()->create(['name' => 'Justin Ribarich', 'email' => 'demo@ribarichh.com']);

        $photos = new DemoImageFactory;
        $recipes = require __DIR__.'/data/demo-recipes.php';
        $publishedAt = now()->subDays(count($recipes) * 3);

        foreach ($recipes as $definition) {
            if (Recipe::query()->where('title', $definition['title'])->exists()) {
                continue;
            }

            $publishedAt = $publishedAt->copy()->addDays(3);
            $status = $definition['status'] ?? RecipeStatus::Published->value;

            $recipe = $writer->create([
                'title' => $definition['title'],
                'description' => $definition['description'] ?? null,
                'notes' => $definition['notes'] ?? null,
                'category_id' => $this->categoryId($definition['category'] ?? null),
                'tags' => $definition['tags'] ?? [],
                'prep_minutes' => $definition['prep'] ?? null,
                'cook_minutes' => $definition['cook'] ?? null,
                'total_minutes_override' => $definition['total_override'] ?? null,
                'servings' => $definition['servings'] ?? null,
                'servings_label' => $definition['servings_label'] ?? null,
                'calories' => $definition['calories'] ?? null,
                'source_name' => $definition['source_name'] ?? null,
                'is_favorite' => $definition['favorite'] ?? false,
                'status' => $status,
                'published_at' => $status === RecipeStatus::Published->value ? $publishedAt : null,
                'ingredients' => $this->ingredients($definition['ingredients']),
                'steps' => $this->steps($definition['steps']),
            ], $author->id);

            $this->attachPhotos($recipe, $photos, $images);

            $this->command?->getOutput()->writeln("  <fg=gray>seeded</> {$recipe->title}");
        }
    }

    private function attachPhotos(Recipe $recipe, DemoImageFactory $photos, ImageStore $images): void
    {
        // A hero for every recipe, and a second shot for a few so the gallery
        // and lightbox have something to show during development.
        $count = crc32($recipe->slug) % 3 === 0 ? 2 : 1;

        for ($i = 0; $i < $count; $i++) {
            $binary = $photos->make($recipe->slug.'-'.$i);

            $image = $images->storeBinary($binary, $recipe->slug.'-'.$i.'.jpg', $recipe->title);

            $image->forceFill([
                'recipe_id' => $recipe->id,
                'is_hero' => $i === 0,
                'sort_order' => $i,
                'caption' => $i === 0 ? null : 'Ready to serve',
            ])->save();
        }
    }

    private function categoryId(?string $slug): ?int
    {
        return $slug === null ? null : Category::query()->where('slug', $slug)->value('id');
    }

    /**
     * @param  list<string>  $lines
     * @return list<array<string, mixed>>
     */
    private function ingredients(array $lines): array
    {
        return array_map(function (string $line): array {
            $parts = array_map(trim(...), explode('|', $line));

            $quantityText = $parts[0] ?? '';
            $quantity = Quantity::parse($quantityText);

            return [
                'quantity' => $quantity,
                // Store the friendly rendering: "0.5" becomes "1/2".
                'quantity_display' => $quantity !== null ? Quantity::format($quantity) : ($quantityText ?: null),
                'unit' => ($parts[1] ?? '') ?: null,
                'name' => $parts[2] ?? '',
                'note' => ($parts[3] ?? '') ?: null,
            ];
        }, $lines);
    }

    /**
     * @param  list<array{0: string, 1: int|null}>  $steps
     * @return list<array<string, mixed>>
     */
    private function steps(array $steps): array
    {
        return array_map(fn (array $step): array => [
            'instruction' => $step[0],
            'timer_seconds' => $step[1],
        ], $steps);
    }
}
