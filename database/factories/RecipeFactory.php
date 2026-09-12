<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecipeStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(12),
            'prep_minutes' => fake()->numberBetween(5, 30),
            'cook_minutes' => fake()->numberBetween(10, 60),
            'servings' => fake()->numberBetween(2, 8),
            'status' => RecipeStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(0, 200)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => RecipeStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (): array => ['is_favorite' => true]);
    }

    public function withContent(int $ingredients = 5, int $steps = 4): static
    {
        return $this->afterCreating(function (Recipe $recipe) use ($ingredients, $steps): void {
            for ($i = 0; $i < $ingredients; $i++) {
                $recipe->ingredients()->create([
                    'quantity' => fake()->randomElement([0.5, 1, 1.5, 2, 3]),
                    'quantity_display' => fake()->randomElement(['1/2', '1', '1 1/2', '2', '3']),
                    'unit' => fake()->randomElement(['cup', 'tbsp', 'tsp', 'g', null]),
                    'name' => fake()->words(2, true),
                    'sort_order' => $i,
                ]);
            }

            for ($i = 0; $i < $steps; $i++) {
                $recipe->steps()->create([
                    'instruction' => fake()->sentence(14),
                    'sort_order' => $i,
                ]);
            }
        });
    }
}
