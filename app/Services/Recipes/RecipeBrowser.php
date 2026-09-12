<?php

declare(strict_types=1);

namespace App\Services\Recipes;

use App\Http\Presenters\RecipePresenter;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared query building for every public listing: /recipes, a category page and
 * a tag page are the same view with one filter pre-applied.
 */
final class RecipeBrowser
{
    public const PER_PAGE = 24;

    public function __construct(private readonly RecipeSearch $search) {}

    /**
     * @param  array<string, mixed>  $forced  Filters the page itself imposes.
     * @return array<string, mixed>
     */
    public function browse(Request $request, array $forced = []): array
    {
        $filters = $this->filters($request, $forced);

        $query = Recipe::query()
            ->published()
            ->with(['heroImage', 'category']);

        $this->search->apply($query, $filters['q']);

        if ($filters['category'] !== null) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        foreach ($filters['tags'] as $slug) {
            // Successive whereHas gives AND semantics: a recipe must carry
            // every selected tag, which is what a filter panel implies.
            $query->whereHas('tags', fn ($q) => $q->where('slug', $slug));
        }

        if ($filters['favorites']) {
            $query->favorite();
        }

        $this->search->sort($query, $filters['sort'], $filters['q'] !== null);

        /** @var LengthAwarePaginator<int, Recipe> $paginator */
        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();

        return [
            'recipes' => [
                'data' => collect($paginator->items())->map(RecipePresenter::card(...))->all(),
                'meta' => [
                    'currentPage' => $paginator->currentPage(),
                    'lastPage' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'perPage' => $paginator->perPage(),
                    'nextPageUrl' => $paginator->nextPageUrl(),
                    'prevPageUrl' => $paginator->previousPageUrl(),
                ],
            ],
            'filters' => $filters,
            'facets' => $this->facets(),
        ];
    }

    /**
     * @param  array<string, mixed>  $forced
     * @return array{q: string|null, category: string|null, tags: list<string>, sort: string, favorites: bool}
     */
    private function filters(Request $request, array $forced): array
    {
        $tags = $request->query('tags', []);
        $tags = is_array($tags) ? $tags : explode(',', (string) $tags);
        $tags = array_values(array_filter(array_map(
            fn ($tag): string => trim((string) $tag),
            array_slice($tags, 0, 8)
        )));

        $sort = (string) $request->query('sort', '');
        $allowed = ['newest', 'oldest', 'title', 'quickest', 'relevance'];

        $q = trim((string) $request->query('q', ''));

        return [
            'q' => $q !== '' ? mb_substr($q, 0, 120) : null,
            'category' => $forced['category'] ?? ($request->query('category') ? (string) $request->query('category') : null),
            'tags' => $forced['tags'] ?? $tags,
            'sort' => in_array($sort, $allowed, true) ? $sort : ($q !== '' ? 'relevance' : 'newest'),
            'favorites' => $forced['favorites'] ?? $request->boolean('favorites'),
        ];
    }

    /**
     * Filter options, limited to taxonomy that actually has published recipes
     * so the panel never offers a dead end.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function facets(): array
    {
        $categories = Category::query()
            ->withCount(['recipes' => fn ($q) => $q->published()])
            ->whereHas('recipes', fn ($q) => $q->published())
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Category $c): array => [
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'count' => $c->recipes_count,
            ])->all();

        $tags = Tag::query()
            ->withCount(['recipes' => fn ($q) => $q->published()])
            ->whereHas('recipes', fn ($q) => $q->published())
            ->orderByDesc('recipes_count')->orderBy('name')
            ->limit(40)
            ->get()
            ->map(fn (Tag $t): array => [
                'name' => $t->name,
                'slug' => $t->slug,
                'count' => $t->recipes_count,
            ])->all();

        return ['categories' => $categories, 'tags' => $tags];
    }
}
