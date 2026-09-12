<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Recipe;
use App\Support\Quantity;

/**
 * Schema.org Recipe JSON-LD for public recipe pages.
 *
 * Emitted server-side from the Blade root template so it is present in the
 * initial HTML rather than appearing only after React hydrates.
 *
 * @see https://schema.org/Recipe
 */
final class RecipeStructuredData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Recipe $recipe): array
    {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => $recipe->title,
            'description' => $recipe->description,
            'url' => route('recipes.show', $recipe->slug),
            'datePublished' => $recipe->published_at?->toDateString(),
            'dateModified' => $recipe->updated_at?->toDateString(),
            'recipeCategory' => $recipe->category?->name,
            'keywords' => $recipe->tags->pluck('name')->implode(', ') ?: null,
            'recipeYield' => self::yield($recipe),
            'prepTime' => self::iso8601($recipe->prep_minutes),
            'cookTime' => self::iso8601($recipe->cook_minutes),
            'totalTime' => self::iso8601($recipe->totalMinutes()),
            'recipeIngredient' => self::ingredients($recipe),
            'recipeInstructions' => self::instructions($recipe),
            'image' => self::images($recipe),
            'author' => self::author($recipe),
            'nutrition' => self::nutrition($recipe),
        ], static fn ($value): bool => $value !== null && $value !== [] && $value !== '');

        if ($recipe->source_url !== null) {
            $data['isBasedOn'] = $recipe->source_url;
        }

        return $data;
    }

    private static function yield(Recipe $recipe): ?string
    {
        if ($recipe->servings === null) {
            return null;
        }

        return trim($recipe->servings.' '.($recipe->servings_label ?: 'servings'));
    }

    private static function iso8601(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return 'PT'.($hours > 0 ? $hours.'H' : '').($rest > 0 || $hours === 0 ? $rest.'M' : '');
    }

    /**
     * @return list<string>
     */
    private static function ingredients(Recipe $recipe): array
    {
        return $recipe->ingredients
            ->map(function ($ingredient): string {
                $quantity = Quantity::displayFor($ingredient->quantity, $ingredient->quantity_display);

                return trim(implode(' ', array_filter([
                    $quantity,
                    $ingredient->unit,
                    $ingredient->name,
                    $ingredient->note !== null ? '('.$ingredient->note.')' : null,
                ])));
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function instructions(Recipe $recipe): array
    {
        return $recipe->steps
            ->values()
            ->map(fn ($step, int $index): array => array_filter([
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'text' => $step->instruction,
                'url' => route('recipes.show', $recipe->slug).'#step-'.($index + 1),
            ]))
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function images(Recipe $recipe): array
    {
        return $recipe->images
            ->map(fn ($image): ?string => $image->displayUrl())
            ->filter()
            ->map(fn (string $url): string => str_starts_with($url, 'http') ? $url : url($url))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    private static function author(Recipe $recipe): ?array
    {
        $name = $recipe->user?->name ?? $recipe->source_name;

        return $name === null ? null : ['@type' => 'Person', 'name' => $name];
    }

    /**
     * @return array<string, string>|null
     */
    private static function nutrition(Recipe $recipe): ?array
    {
        if ($recipe->calories === null) {
            return null;
        }

        return [
            '@type' => 'NutritionInformation',
            'calories' => $recipe->calories.' calories',
            'servingSize' => '1 '.rtrim($recipe->servings_label ?: 'serving', 's'),
        ];
    }
}
