<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeStep>
 */
class RecipeStepFactory extends Factory
{
    protected $model = RecipeStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'instruction' => fake()->sentence(12),
            'sort_order' => 0,
        ];
    }
}
