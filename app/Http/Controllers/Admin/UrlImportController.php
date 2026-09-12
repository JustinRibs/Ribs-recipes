<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UrlImportRequest;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Services\Import\UrlRecipeImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import from a URL: fetch, parse, then hand the result to an editable
 * preview. Nothing is written until the administrator saves from the editor,
 * which is the ordinary recipe form pre-filled with what was found.
 */
class UrlImportController extends Controller
{
    public function __construct(private readonly UrlRecipeImporter $importer) {}

    public function show(): Response
    {
        $this->authorize('create', Recipe::class);

        return Inertia::render('Admin/Import/Url', [
            'draft' => null,
            'categories' => $this->categories(),
            'allTags' => Tag::query()->orderBy('name')->pluck('name')->all(),
            'meta' => ['title' => 'Import from URL — '.config('app.name')],
        ]);
    }

    public function preview(UrlImportRequest $request): Response|RedirectResponse
    {
        $this->authorize('create', Recipe::class);

        $url = (string) $request->validated('url');

        try {
            $imported = $this->importer->import($url);
        } catch (UnsafeUrlException|FetchFailedException $e) {
            Log::channel('import')->warning('URL import failed', [
                'host' => parse_url($url, PHP_URL_HOST),
                'reason' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['url' => $e->getMessage()]);
        }

        return Inertia::render('Admin/Import/Url', [
            'draft' => [
                'form' => $imported->toFormState(),
                'heroImageUrl' => $imported->heroImageUrl,
                'warnings' => $imported->warnings,
                'extractedVia' => $imported->extractedVia,
                'sourceUrl' => $imported->sourceUrl,
            ],
            'submittedUrl' => $url,
            'categories' => $this->categories(),
            'allTags' => Tag::query()->orderBy('name')->pluck('name')->all(),
            'meta' => ['title' => 'Review import — '.config('app.name')],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(): array
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
