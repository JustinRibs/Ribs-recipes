<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Recipes\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly SlugGenerator $slugs) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::query()
                ->withCount('recipes')
                ->orderBy('sort_order')->orderBy('name')
                ->get()
                ->map(fn (Category $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'description' => $c->description,
                    'icon' => $c->icon,
                    'color' => $c->color,
                    'sortOrder' => $c->sort_order,
                    'recipesCount' => $c->recipes_count,
                    'url' => route('categories.show', $c->slug),
                ])
                ->all(),
            'meta' => ['title' => 'Categories — '.config('app.name')],
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validated();

        Category::create([
            ...$data,
            'slug' => $this->slugs->generate(Category::class, $data['slug'] ?? $data['name']),
            'sort_order' => $data['sort_order'] ?? ((int) Category::query()->max('sort_order') + 10),
        ]);

        return back()->with('success', 'Category added.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $data = $request->validated();

        if (filled($data['slug'] ?? null) && $data['slug'] !== $category->slug) {
            $data['slug'] = $this->slugs->generate(Category::class, $data['slug'], $category->id);
        } else {
            unset($data['slug']);
        }

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // Recipes survive: category_id is nulled by the foreign key, so the
        // recipes simply become uncategorised rather than disappearing.
        $category->delete();

        return back()->with('success', "“{$category->name}” removed. Its recipes are now uncategorised.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $ids = collect($request->input('ids', []))->map(fn ($id): int => (int) $id)->filter()->values();

        foreach ($ids as $index => $id) {
            Category::query()->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
        }

        return back();
    }
}
