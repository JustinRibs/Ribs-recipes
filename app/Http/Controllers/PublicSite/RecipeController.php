<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Presenters\RecipePresenter;
use App\Models\Recipe;
use App\Services\Recipes\RecipeBrowser;
use App\Services\Seo\RecipeStructuredData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function __construct(private readonly RecipeBrowser $browser) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Recipes/Index', [
            ...$this->browser->browse($request),
            'heading' => 'All recipes',
            'meta' => [
                'title' => 'Recipes — '.config('app.name'),
                'description' => 'Browse every recipe in the collection. Filter by category, tag or cooking time.',
                'canonical' => route('recipes.index'),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $recipe = Recipe::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'category', 'tags', 'user',
                'ingredients',
                'steps.image',
                'images',
            ])
            ->firstOrFail();

        return Inertia::render('Recipes/Show', [
            'recipe' => RecipePresenter::full($recipe),
            'related' => $this->related($recipe),
            'meta' => [
                'title' => $recipe->title.' — '.config('app.name'),
                'description' => $recipe->description
                    ?? Str::limit('How to make '.$recipe->title.'.', 160),
                'canonical' => route('recipes.show', $recipe->slug),
                'image' => $recipe->heroImage?->displayUrl(),
                'type' => 'article',
                'jsonLd' => RecipeStructuredData::for($recipe),
            ],
        ]);
    }

    /**
     * Recipes worth reading next: same category first, then shared tags.
     *
     * @return list<array<string, mixed>>
     */
    private function related(Recipe $recipe): array
    {
        $tagIds = $recipe->tags->pluck('id');

        return Recipe::query()
            ->published()
            ->whereKeyNot($recipe->id)
            ->with(['heroImage', 'category'])
            ->where(function ($query) use ($recipe, $tagIds): void {
                $query->where('category_id', $recipe->category_id)
                    ->when(
                        $tagIds->isNotEmpty(),
                        fn ($q) => $q->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds))
                    );
            })
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$recipe->category_id])
            ->orderByRaw('COALESCE(published_at, created_at) desc')
            ->limit(6)
            ->get()
            ->map(RecipePresenter::card(...))
            ->all();
    }
}
