<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Presenters\RecipePresenter;
use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The homepage is a set of editorial rails rather than a feed: a spotlight,
 * what is new, what the author has featured, then a rail per category that has
 * enough published recipes to be worth showing.
 */
class HomeController extends Controller
{
    private const RAIL_SIZE = 12;

    /** Categories the homepage always leads with, in this order. */
    private const LEAD_CATEGORIES = ['breakfast', 'dinner'];

    public function __invoke(): Response
    {
        $spotlight = $this->spotlight();

        return Inertia::render('Home', [
            'spotlight' => $spotlight === null ? null : RecipePresenter::card($spotlight),
            'sections' => $this->sections($spotlight?->id),
            'categories' => $this->categoryChips(),
            'totalRecipes' => Recipe::query()->published()->count(),
            'meta' => [
                'title' => config('app.name').' — Good food goes further',
                'description' => 'A personal collection of recipes worth cooking again: Mediterranean plates, high-protein breakfasts, Croatian family cooking and weeknight dinners.',
                'canonical' => route('home'),
                'image' => $spotlight?->heroImage?->displayUrl(),
            ],
        ]);
    }

    private function spotlight(): ?Recipe
    {
        return $this->baseQuery()
            ->whereHas('heroImage')
            ->orderByDesc('is_favorite')
            ->orderByRaw('COALESCE(published_at, created_at) desc')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sections(?int $excludeId): array
    {
        $sections = [];

        $recent = $this->rail(fn ($q) => $q->orderByRaw('COALESCE(published_at, created_at) desc'), $excludeId);

        if ($recent->isNotEmpty()) {
            $sections[] = [
                'key' => 'recent',
                'title' => 'Recently added',
                'subtitle' => 'Fresh out of the kitchen',
                'href' => route('recipes.index', ['sort' => 'newest']),
                'recipes' => $recent->map(RecipePresenter::card(...))->all(),
            ];
        }

        $favorites = $this->rail(
            fn ($q) => $q->favorite()->orderByRaw('COALESCE(published_at, created_at) desc'),
            $excludeId
        );

        if ($favorites->isNotEmpty()) {
            $sections[] = [
                'key' => 'favorites',
                'title' => 'Favorites',
                'subtitle' => 'The ones that get cooked on repeat',
                'href' => route('recipes.index', ['favorites' => 1]),
                'recipes' => $favorites->map(RecipePresenter::card(...))->all(),
            ];
        }

        foreach ($this->railCategories() as $category) {
            $recipes = $this->rail(
                fn ($q) => $q->where('category_id', $category->id)
                    ->orderByRaw('COALESCE(published_at, created_at) desc'),
                null
            );

            if ($recipes->count() < 2) {
                continue;
            }

            $sections[] = [
                'key' => 'category-'.$category->slug,
                'title' => $category->name,
                'subtitle' => $category->description,
                'href' => route('categories.show', $category->slug),
                'recipes' => $recipes->map(RecipePresenter::card(...))->all(),
            ];
        }

        return $sections;
    }

    /**
     * Lead categories first, then any other category with published recipes.
     *
     * @return Collection<int, Category>
     */
    private function railCategories(): Collection
    {
        // whereHas rather than HAVING: SQLite rejects a HAVING clause on a
        // non-aggregate query, and this reads better anyway.
        $categories = Category::query()
            ->withCount(['recipes' => fn ($q) => $q->published()])
            ->whereHas('recipes', fn ($q) => $q->published(), '>=', 2)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $lead = collect(self::LEAD_CATEGORIES)
            ->map(fn (string $slug) => $categories->firstWhere('slug', $slug))
            ->filter();

        return $lead->concat($categories->whereNotIn('slug', self::LEAD_CATEGORIES))->take(5);
    }

    /**
     * @param  \Closure(Builder<Recipe>): mixed  $constraint
     * @return Collection<int, Recipe>
     */
    private function rail(\Closure $constraint, ?int $excludeId): Collection
    {
        $query = $this->baseQuery();

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        $constraint($query);

        return $query->limit(self::RAIL_SIZE)->get();
    }

    /**
     * @return Builder<Recipe>
     */
    private function baseQuery()
    {
        return Recipe::query()
            ->published()
            ->with(['heroImage', 'category']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryChips(): array
    {
        return Category::query()
            ->withCount(['recipes' => fn ($q) => $q->published()])
            ->whereHas('recipes', fn ($q) => $q->published())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'color' => $category->color,
                'count' => $category->recipes_count,
                'url' => route('categories.show', $category->slug),
            ])
            ->all();
    }
}
