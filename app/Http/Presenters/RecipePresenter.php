<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\Recipe;
use App\Support\Duration;

/**
 * Explicit shapes for the props each page needs.
 *
 * Cards carry only what a grid renders (the single biggest win for payload
 * size on the homepage), while the detail and admin-form shapes are complete.
 */
final class RecipePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function card(Recipe $recipe): array
    {
        $total = $recipe->totalMinutes();

        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'slug' => $recipe->slug,
            'description' => $recipe->description,
            'url' => route('recipes.show', $recipe->slug),
            'image' => ImagePresenter::make($recipe->relationLoaded('heroImage') ? $recipe->heroImage : null),
            'category' => self::category($recipe),
            'totalMinutes' => $total,
            'totalTime' => Duration::humanise($total),
            'servings' => $recipe->servings,
            'isFavorite' => $recipe->is_favorite,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function full(Recipe $recipe): array
    {
        $total = $recipe->totalMinutes();

        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'slug' => $recipe->slug,
            'description' => $recipe->description,
            'notes' => $recipe->notes,
            'url' => route('recipes.show', $recipe->slug),
            'category' => self::category($recipe),
            'tags' => $recipe->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'url' => route('tags.show', $tag->slug),
            ])->values()->all(),
            'author' => $recipe->user?->name,
            'prepMinutes' => $recipe->prep_minutes,
            'cookMinutes' => $recipe->cook_minutes,
            'totalMinutes' => $total,
            'prepTime' => Duration::humanise($recipe->prep_minutes),
            'cookTime' => Duration::humanise($recipe->cook_minutes),
            'totalTime' => Duration::humanise($total),
            'servings' => $recipe->servings,
            'servingsLabel' => $recipe->servings_label ?: 'servings',
            'calories' => $recipe->calories,
            'sourceUrl' => $recipe->source_url,
            'sourceName' => $recipe->source_name,
            'isFavorite' => $recipe->is_favorite,
            'publishedAt' => $recipe->published_at?->toIso8601String(),
            'updatedAt' => $recipe->updated_at?->toIso8601String(),
            'heroImage' => ImagePresenter::make($recipe->heroImage),
            'gallery' => ImagePresenter::collection($recipe->galleryImages),
            'ingredients' => $recipe->ingredients->map(fn ($i): array => [
                'id' => $i->id,
                'quantity' => $i->quantity,
                'quantityDisplay' => $i->quantity_display,
                'unit' => $i->unit,
                'name' => $i->name,
                'note' => $i->note,
            ])->values()->all(),
            'steps' => $recipe->steps->map(fn ($s): array => [
                'id' => $s->id,
                'instruction' => $s->instruction,
                'timerSeconds' => $s->timer_seconds,
                'image' => ImagePresenter::make($s->relationLoaded('image') ? $s->image : null),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminRow(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'slug' => $recipe->slug,
            'status' => $recipe->status->value,
            'isFavorite' => $recipe->is_favorite,
            'category' => self::category($recipe),
            'thumb' => $recipe->heroImage?->thumbnailUrl(),
            'totalMinutes' => $recipe->totalMinutes(),
            'updatedAt' => $recipe->updated_at?->toIso8601String(),
            'publishedAt' => $recipe->published_at?->toIso8601String(),
            'deletedAt' => $recipe->deleted_at?->toIso8601String(),
            'editUrl' => route('admin.recipes.edit', $recipe->id),
            'publicUrl' => route('recipes.show', $recipe->slug),
        ];
    }

    /**
     * The editor's working copy — matches the shape the React form posts back.
     *
     * @return array<string, mixed>
     */
    public static function form(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'slug' => $recipe->slug,
            'description' => $recipe->description ?? '',
            'notes' => $recipe->notes ?? '',
            'category_id' => $recipe->category_id,
            'tags' => $recipe->tags->pluck('name')->values()->all(),
            'prep_minutes' => $recipe->prep_minutes,
            'cook_minutes' => $recipe->cook_minutes,
            'total_minutes_override' => $recipe->total_minutes_override,
            'servings' => $recipe->servings,
            'servings_label' => $recipe->servings_label ?? '',
            'calories' => $recipe->calories,
            'source_url' => $recipe->source_url ?? '',
            'source_name' => $recipe->source_name ?? '',
            'is_favorite' => $recipe->is_favorite,
            'status' => $recipe->status->value,
            'ingredients' => $recipe->ingredients->map(fn ($i): array => [
                'quantity_display' => $i->quantity_display ?? '',
                'unit' => $i->unit ?? '',
                'name' => $i->name,
                'note' => $i->note ?? '',
            ])->values()->all(),
            'steps' => $recipe->steps->map(fn ($s): array => [
                'instruction' => $s->instruction,
                'timer_seconds' => $s->timer_seconds,
            ])->values()->all(),
            'images' => $recipe->images->map(fn ($image): array => [
                ...(ImagePresenter::make($image) ?? []),
                'caption' => $image->caption ?? '',
                'alt' => $image->alt ?? '',
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function category(Recipe $recipe): ?array
    {
        $category = $recipe->relationLoaded('category') ? $recipe->category : null;

        if ($category === null) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'icon' => $category->icon,
            'color' => $category->color,
            'url' => route('categories.show', $category->slug),
        ];
    }
}
