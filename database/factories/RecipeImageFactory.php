<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImageSource;
use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeImage>
 */
class RecipeImageFactory extends Factory
{
    protected $model = RecipeImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'source' => ImageSource::Remote,
            'url' => 'https://example.test/photo.jpg',
            'width' => 1200,
            'height' => 800,
            'mime' => 'image/jpeg',
            'is_hero' => true,
            'sort_order' => 0,
        ];
    }
}
