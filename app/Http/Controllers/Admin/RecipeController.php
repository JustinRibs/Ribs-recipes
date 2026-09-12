<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\RecipePresenter;
use App\Http\Requests\Admin\RecipeRequest;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Services\Recipes\RecipeSearch;
use App\Services\Recipes\RecipeWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly RecipeWriter $writer,
        private readonly RecipeSearch $search,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Recipe::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')) ?: null,
            'category' => $request->query('category') ? (string) $request->query('category') : null,
            'tag' => $request->query('tag') ? (string) $request->query('tag') : null,
            'status' => in_array($request->query('status'), ['draft', 'published'], true)
                ? (string) $request->query('status') : null,
            'favorites' => $request->boolean('favorites'),
            'trashed' => $request->boolean('trashed'),
            'sort' => (string) $request->query('sort', 'updated'),
        ];

        $query = Recipe::query()->with(['heroImage', 'category']);

        if ($filters['trashed']) {
            $query->onlyTrashed();
        }

        $this->search->apply($query, $filters['q']);

        $query
            ->when($filters['category'], fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($filters['tag'], fn ($q, $slug) => $q->whereHas('tags', fn ($t) => $t->where('slug', $slug)))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['favorites'], fn ($q) => $q->favorite());

        match ($filters['sort']) {
            'title' => $query->orderBy('title'),
            'created' => $query->orderByDesc('created_at'),
            'published' => $query->orderByRaw('published_at is null, published_at desc'),
            default => $query->orderByDesc('updated_at'),
        };

        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Recipes/Index', [
            'recipes' => [
                'data' => collect($paginator->items())->map(RecipePresenter::adminRow(...))->all(),
                'meta' => [
                    'currentPage' => $paginator->currentPage(),
                    'lastPage' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'nextPageUrl' => $paginator->nextPageUrl(),
                    'prevPageUrl' => $paginator->previousPageUrl(),
                ],
            ],
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
            'trashedCount' => Recipe::onlyTrashed()->count(),
            'meta' => ['title' => 'Recipes — '.config('app.name')],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Recipe::class);

        return Inertia::render('Admin/Recipes/Form', [
            'recipe' => null,
            'categories' => $this->categoryOptions(),
            'allTags' => Tag::query()->orderBy('name')->pluck('name')->all(),
            'meta' => ['title' => 'New recipe — '.config('app.name')],
        ]);
    }

    public function store(RecipeRequest $request): RedirectResponse
    {
        $this->authorize('create', Recipe::class);

        $recipe = $this->writer->create($request->validated(), $request->user()?->id);

        return redirect()
            ->route('admin.recipes.edit', $recipe)
            ->with('success', "“{$recipe->title}” saved.");
    }

    public function edit(Recipe $recipe): Response
    {
        $this->authorize('update', $recipe);

        $recipe->load(['ingredients', 'steps', 'images', 'tags', 'category']);

        return Inertia::render('Admin/Recipes/Form', [
            'recipe' => RecipePresenter::form($recipe),
            'categories' => $this->categoryOptions(),
            'allTags' => Tag::query()->orderBy('name')->pluck('name')->all(),
            'meta' => ['title' => $recipe->title.' — '.config('app.name')],
        ]);
    }

    public function update(RecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $this->writer->update($recipe, $request->validated());

        return back()->with('success', 'Saved.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('delete', $recipe);

        $this->writer->delete($recipe);

        return redirect()
            ->route('admin.recipes.index')
            ->with('success', "“{$recipe->title}” moved to the trash.");
    }

    public function restore(Recipe $recipe): RedirectResponse
    {
        // Bound with ->withTrashed(), so a trashed recipe resolves here.
        $this->authorize('restore', $recipe);

        $this->writer->restore($recipe);

        return back()->with('success', "“{$recipe->title}” restored.");
    }

    public function forceDestroy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('forceDelete', $recipe);

        $title = $recipe->title;
        $this->writer->forceDelete($recipe);

        return back()->with('success', "“{$title}” deleted permanently.");
    }

    public function duplicate(Recipe $recipe): RedirectResponse
    {
        $this->authorize('create', Recipe::class);

        $copy = $this->writer->duplicate($recipe, request()->user()?->id);

        return redirect()
            ->route('admin.recipes.edit', $copy)
            ->with('success', 'Duplicated. This copy is a draft.');
    }

    public function toggleFavorite(Recipe $recipe): RedirectResponse
    {
        $this->authorize('feature', $recipe);

        $recipe->update(['is_favorite' => ! $recipe->is_favorite]);

        return back()->with('success', $recipe->is_favorite
            ? "“{$recipe->title}” is now a favorite."
            : "“{$recipe->title}” is no longer a favorite.");
    }

    public function togglePublished(Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $publishing = $recipe->status !== RecipeStatus::Published;

        if ($publishing && ($recipe->ingredients()->count() === 0 || $recipe->steps()->count() === 0)) {
            return back()->with('error', 'Add ingredients and steps before publishing.');
        }

        $this->writer->update($recipe, [
            'status' => $publishing ? RecipeStatus::Published : RecipeStatus::Draft,
        ]);

        return back()->with('success', $publishing ? 'Published.' : 'Moved back to drafts.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'color'])
            ->map(fn (Category $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'color' => $c->color,
            ])
            ->all();
    }
}
