<?php

declare(strict_types=1);

namespace App\Services\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maintains the SQLite FTS5 index for recipes.
 *
 * A recipe's searchable text spans four tables, which SQLite triggers cannot
 * join, so the denormalised row is rewritten from PHP whenever a recipe is
 * saved or deleted. On non-SQLite drivers every method is a no-op and search
 * falls back to indexed LIKE matching.
 */
final class RecipeSearchIndex
{
    public const TABLE = 'recipes_fts';

    private ?bool $available = null;

    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        return $this->available = DB::connection()->getDriverName() === 'sqlite'
            && Schema::hasTable(self::TABLE);
    }

    public function sync(Recipe $recipe): void
    {
        if (! $this->available()) {
            return;
        }

        $recipe->loadMissing(['ingredients', 'category', 'tags']);

        $this->forget($recipe->id);

        DB::table(self::TABLE)->insert([
            'recipe_id' => $recipe->id,
            'title' => $recipe->title,
            'description' => (string) $recipe->description,
            'ingredients' => $recipe->ingredients
                ->map(fn ($i): string => trim($i->name.' '.(string) $i->note))
                ->implode(' '),
            'category' => (string) $recipe->category?->name,
            'tags' => $recipe->tags->pluck('name')->implode(' '),
            'notes' => (string) $recipe->notes,
        ]);
    }

    public function forget(int $recipeId): void
    {
        if (! $this->available()) {
            return;
        }

        // FTS5 columns carry no affinity, so compare on an explicit cast
        // rather than relying on SQLite coercing text to integer.
        DB::table(self::TABLE)->whereRaw('CAST(recipe_id AS INTEGER) = ?', [$recipeId])->delete();
    }

    /**
     * Rebuild the whole index. Used by `php artisan recipes:reindex` and after
     * a bulk import.
     */
    public function rebuild(): int
    {
        if (! $this->available()) {
            return 0;
        }

        DB::table(self::TABLE)->delete();

        $count = 0;

        Recipe::query()
            ->with(['ingredients', 'category', 'tags'])
            ->chunkById(100, function ($recipes) use (&$count): void {
                foreach ($recipes as $recipe) {
                    $this->sync($recipe);
                    $count++;
                }
            });

        return $count;
    }
}
