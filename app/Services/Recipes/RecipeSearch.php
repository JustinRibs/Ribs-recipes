<?php

declare(strict_types=1);

namespace App\Services\Recipes;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Query building for the public browse page and admin recipe list.
 *
 * Text matching runs through FTS5 when it is available and degrades to indexed
 * LIKE matching otherwise, so the same call site works on SQLite today and on
 * PostgreSQL/MariaDB later.
 */
final class RecipeSearch
{
    public function __construct(private readonly RecipeSearchIndex $index) {}

    /**
     * @param  Builder<Recipe>  $query
     */
    public function apply(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $this->useFts()
            ? $this->applyFts($query, $term)
            : $this->applyLike($query, $term);
    }

    /**
     * @param  Builder<Recipe>  $query
     */
    public function sort(Builder $query, ?string $sort, bool $hasTerm): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderByRaw('COALESCE(published_at, created_at) asc')->orderBy('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            'quickest' => $query
                ->orderByRaw('COALESCE(total_minutes_override, NULLIF(COALESCE(prep_minutes,0) + COALESCE(cook_minutes,0), 0), 999999) asc')
                ->orderBy('title'),
            'relevance' => $hasTerm && $this->useFts()
                ? $query->orderBy('fts_rank')->orderByRaw('COALESCE(published_at, created_at) desc')
                : $query->orderByRaw('COALESCE(published_at, created_at) desc')->orderByDesc('id'),
            default => $query->orderByRaw('COALESCE(published_at, created_at) desc')->orderByDesc('id'),
        };
    }

    private function useFts(): bool
    {
        $configured = config('ribs.search.driver', 'auto');

        if ($configured === 'like') {
            return false;
        }

        return $this->index->available();
    }

    /**
     * @param  Builder<Recipe>  $query
     */
    private function applyFts(Builder $query, string $term): Builder
    {
        $table = RecipeSearchIndex::TABLE;

        return $query
            ->addSelect([
                'fts_rank' => DB::table($table)
                    ->selectRaw('rank')
                    ->whereRaw('CAST(recipe_id AS INTEGER) = recipes.id')
                    ->whereRaw("{$table} MATCH ?", [$this->toMatchExpression($term)])
                    ->limit(1),
            ])
            ->whereIn('recipes.id', function ($sub) use ($table, $term): void {
                $sub->from($table)
                    ->selectRaw('CAST(recipe_id AS INTEGER)')
                    ->whereRaw("{$table} MATCH ?", [$this->toMatchExpression($term)]);
            });
    }

    /**
     * Turn free text into a safe FTS5 MATCH expression.
     *
     * Every token is quoted (so FTS5 operators typed by a visitor are treated
     * as literal text) and the final token gets a prefix wildcard, which is
     * what makes search-as-you-type feel instant.
     */
    private function toMatchExpression(string $term): string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_slice($tokens, 0, 12);

        if ($tokens === []) {
            return '""';
        }

        $last = array_key_last($tokens);

        foreach ($tokens as $i => $token) {
            $quoted = '"'.str_replace('"', '""', $token).'"';
            $tokens[$i] = $i === $last ? $quoted.' *' : $quoted;
        }

        return implode(' ', $tokens);
    }

    /**
     * @param  Builder<Recipe>  $query
     */
    private function applyLike(Builder $query, string $term): Builder
    {
        $words = array_slice(preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);

        foreach ($words as $word) {
            $like = '%'.Str::lower(addcslashes($word, '%_\\')).'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw('lower(recipes.title) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(recipes.description, "")) like ?', [$like])
                    ->orWhereHas('ingredients', fn ($i) => $i->whereRaw('lower(name) like ?', [$like]))
                    ->orWhereHas('category', fn ($c) => $c->whereRaw('lower(name) like ?', [$like]))
                    ->orWhereHas('tags', fn ($t) => $t->whereRaw('lower(name) like ?', [$like]));
            });
        }

        return $query;
    }
}
