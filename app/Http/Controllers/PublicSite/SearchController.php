<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Services\Recipes\RecipeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only suggestion endpoint behind the search overlay.
 *
 * Deliberately tiny: the overlay wants six titles and a thumbnail as fast as
 * possible, not a full page payload. It modifies nothing, so it lives on the
 * public side of the application.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, RecipeSearch $search): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $query = Recipe::query()->published()->with(['heroImage', 'category']);

        $search->apply($query, mb_substr($term, 0, 120));
        $search->sort($query, 'relevance', true);

        $results = $query->limit(6)->get()->map(fn (Recipe $recipe): array => [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'url' => route('recipes.show', $recipe->slug),
            'category' => $recipe->category?->name,
            'thumb' => $recipe->heroImage?->thumbnailUrl(),
        ])->all();

        return response()->json(['results' => $results])
            ->header('Cache-Control', 'private, max-age=30');
    }
}
