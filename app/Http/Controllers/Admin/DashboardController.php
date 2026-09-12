<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\RecipePresenter;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $counts = Recipe::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as published', [RecipeStatus::Published->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as drafts', [RecipeStatus::Draft->value])
            ->selectRaw('sum(case when is_favorite = 1 then 1 else 0 end) as favorites')
            ->first();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'recipes' => (int) ($counts->total ?? 0),
                'published' => (int) ($counts->published ?? 0),
                'drafts' => (int) ($counts->drafts ?? 0),
                'favorites' => (int) ($counts->favorites ?? 0),
                'categories' => Category::query()->count(),
                'tags' => Tag::query()->count(),
                'trashed' => Recipe::onlyTrashed()->count(),
            ],
            'recentlyEdited' => Recipe::query()
                ->with(['heroImage', 'category'])
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(RecipePresenter::adminRow(...))
                ->all(),
            'drafts' => Recipe::query()
                ->draft()
                ->with(['heroImage', 'category'])
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get()
                ->map(RecipePresenter::adminRow(...))
                ->all(),
            'meta' => ['title' => 'Dashboard — '.config('app.name')],
        ]);
    }
}
