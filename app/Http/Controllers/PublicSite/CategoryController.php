<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Recipes\RecipeBrowser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly RecipeBrowser $browser) {}

    public function show(Request $request, Category $category): Response
    {
        return Inertia::render('Recipes/Index', [
            ...$this->browser->browse($request, ['category' => $category->slug]),
            'heading' => $category->name,
            'lede' => $category->description,
            'lockedFilter' => ['type' => 'category', 'name' => $category->name, 'slug' => $category->slug],
            'meta' => [
                'title' => $category->name.' recipes — '.config('app.name'),
                'description' => $category->description ?? "Every {$category->name} recipe in the collection.",
                'canonical' => route('categories.show', $category->slug),
            ],
        ]);
    }
}
